<?php
// Extension: ocs_novaposhta. Resumable, paged loading of one Nova Poshta directory into our tables. Owner: SPEC-085.
// Pages of <= 500 records, only changed rows are written (hash), a cursor in oc_ocs_np_sync lets an interrupted run continue,
// rows missing from a finished run are deactivated, never deleted (REQ-NP-002..004).

namespace Ocs\Novaposhta;

class Sync {
	public static $tables = array('areas' => 'ocs_np_area', 'cities' => 'ocs_np_city', 'warehouses' => 'ocs_np_warehouse');

	private static $columns = array(
		'areas'      => array('ref', 'name_uk', 'name_ru'),
		'cities'     => array('ref', 'area_ref', 'name_uk', 'name_ru', 'search_uk', 'search_ru', 'type_uk', 'type_ru'),
		'warehouses' => array('ref', 'city_ref', 'number', 'type', 'name_uk', 'name_ru', 'search', 'schedule', 'max_weight', 'lat', 'lng')
	);

	private $db;
	private $prefix;
	private $client;
	private $limit;
	private $pause;
	private $clock;
	private $sleep;

	/**
	 * @param array $options limit (<=500), pause_ms, clock (fn(): int), sleep (fn(int $microseconds))
	 */
	public function __construct(Db $db, $prefix, Client $client, array $options = array()) {
		$this->db = $db;
		$this->prefix = $prefix;
		$this->client = $client;
		$this->limit = max(10, min(500, isset($options['limit']) ? (int)$options['limit'] : 500));
		$this->pause = max(0, isset($options['pause_ms']) ? (int)$options['pause_ms'] : 200);
		$this->clock = isset($options['clock']) ? $options['clock'] : 'time';
		$this->sleep = isset($options['sleep']) ? $options['sleep'] : 'usleep';
	}

	public function table($entity) {
		return $this->prefix . self::$tables[$entity];
	}

	/** Cursor and status of one directory (defaults when it never ran). */
	public function state($entity) {
		$this->check($entity);

		$rows = $this->db->rows("SELECT * FROM `" . $this->prefix . "ocs_np_sync` WHERE entity = '" . $entity . "'");

		return $rows ? $rows[0] : array('entity' => $entity, 'run_id' => 0, 'page' => 1, 'status' => 'idle', 'started_at' => 0, 'finished_at' => 0, 'rows_run' => 0, 'rows_total' => 0, 'error' => '');
	}

	/** Forget the cursor: the next run starts from page 1 (REQ-NP-006). */
	public function reset($entity) {
		$state = $this->state($entity);
		$state['status'] = 'idle';
		$state['page'] = 1;
		$state['rows_run'] = 0;
		$state['error'] = '';
		$this->save($state);
	}

	/**
	 * Load pages until the directory ends or the time budget is used.
	 *
	 * @param int  $budget seconds for this call, 0 = no limit
	 * @param bool $dry    fetch and count only, write nothing
	 * @return array status (done|partial|error|locked), rows, page, error
	 */
	public function run($entity, $budget = 0, $dry = false) {
		$this->check($entity);

		if (!$dry && !$this->db->lock('ocs_np_sync_' . $entity)) {
			return array('status' => 'locked', 'rows' => 0, 'page' => 0, 'error' => '');
		}

		try {
			return $this->loop($entity, (int)$budget, $dry);
		} finally {
			if (!$dry) {
				$this->db->unlock('ocs_np_sync_' . $entity);
			}
		}
	}

	private function loop($entity, $budget, $dry) {
		$now = call_user_func($this->clock);
		$state = $this->state($entity);

		$resume = !$dry && in_array($state['status'], array('running', 'error'), true) && (int)$state['run_id'] > 0 && $now - (int)$state['started_at'] < 172800;

		if (!$resume) {
			$state = array_merge($state, array('run_id' => max($now, (int)$state['run_id'] + 1), 'page' => 1, 'status' => 'running', 'started_at' => $now, 'rows_run' => 0, 'error' => ''));
		}

		$state['status'] = 'running';
		$state['error'] = '';

		if (!$dry) {
			$this->save($state);
		}

		$started = $now;
		$counted = 0;

		while (true) {
			if ($budget > 0 && call_user_func($this->clock) - $started >= $budget) {
				return array('status' => 'partial', 'rows' => $counted, 'page' => (int)$state['page'], 'error' => '');
			}

			try {
				$data = $this->client->page($entity, (int)$state['page'], $this->limit);
			} catch (\Exception $e) {
				$state['status'] = 'error';
				// page and progress first, the cause follows (the column holds 255 chars)
					$state['error'] = mb_substr('p.' . (int)$state['page'] . ', +' . (int)$state['rows_run'] . ' rows: ' . $e->getMessage(), 0, 255, 'UTF-8');

				if (!$dry) {
					$this->save($state);
				}

				return array('status' => 'error', 'rows' => $counted, 'page' => (int)$state['page'], 'error' => $state['error']);
			}

			$rows = $this->rows($entity, $data);

			if (!$dry) {
				$this->upsert($entity, $rows, (int)$state['run_id']);
			}

			$counted += count($rows);
			$state['rows_run'] = (int)$state['rows_run'] + count($rows);
			$state['page'] = (int)$state['page'] + 1;

			if (count($data) < $this->limit) {
				break;
			}

			if (!$dry) {
				$this->save($state);
			}

			if ($this->pause) {
				call_user_func($this->sleep, $this->pause * 1000);
			}
		}

		if ($dry) {
			return array('status' => 'done', 'rows' => $counted, 'page' => (int)$state['page'], 'error' => '');
		}

		return $this->finish($entity, $state, $counted);
	}

	private function finish($entity, array $state, $counted) {
		$table = $this->table($entity);
		$run = (int)$state['run_id'];

		$active = (int)$this->db->rows("SELECT COUNT(*) AS c FROM `" . $table . "` WHERE is_active = 1")[0]['c'];
		$stale = (int)$this->db->rows("SELECT COUNT(*) AS c FROM `" . $table . "` WHERE is_active = 1 AND run_id <> " . $run)[0]['c'];

		if ($active >= 20 && $stale * 2 > $active) {
			// a response that lost more than half of the directory is far more likely an API fault than reality: keep the old rows
			$state['status'] = 'error';
			$state['error'] = 'Suspicious run: ' . $stale . ' of ' . $active . ' rows missing, nothing deactivated';
			$this->save($state);

			return array('status' => 'error', 'rows' => $counted, 'page' => (int)$state['page'], 'error' => $state['error']);
		}

		$this->db->exec("UPDATE `" . $table . "` SET is_active = 0 WHERE is_active = 1 AND run_id <> " . $run);

		if ($entity == 'warehouses') {
			$this->db->exec("UPDATE `" . $this->table('cities') . "` SET warehouses_count = (SELECT COUNT(*) FROM `" . $table . "` WHERE `" . $table . "`.city_ref = `" . $this->table('cities') . "`.ref AND `" . $table . "`.is_active = 1 AND `" . $table . "`.type <> 'cargo')");
		}

		$state['status'] = 'done';
		$state['finished_at'] = call_user_func($this->clock);
		$state['rows_total'] = (int)$this->db->rows("SELECT COUNT(*) AS c FROM `" . $table . "` WHERE is_active = 1")[0]['c'];
		$state['error'] = '';
		$this->save($state);

		return array('status' => 'done', 'rows' => $counted, 'page' => (int)$state['page'], 'error' => '');
	}

	private function rows($entity, array $data) {
		$rows = array();

		foreach ($data as $record) {
			if (!is_array($record) || empty($record['Ref'])) {
				continue;
			}

			if ($entity == 'warehouses' && !Text::working($record)) {
				continue;
			}

			$row = Text::row($entity, $record);
			$rows[$row['ref']] = $row;
		}

		return array_values($rows);
	}

	/** Changed rows are REPLACEd in one statement, unchanged ones only get the run marker. */
	private function upsert($entity, array $rows, $run) {
		if (!$rows) {
			return;
		}

		$table = $this->table($entity);
		$known = array();

		foreach ($this->db->rows("SELECT ref, hash, is_active FROM `" . $table . "` WHERE ref IN (" . $this->in(array_column($rows, 'ref')) . ")") as $r) {
			$known[$r['ref']] = $r;
		}

		$changed = array();
		$same = array();

		foreach ($rows as $row) {
			$hash = Text::hash($row);

			if (isset($known[$row['ref']]) && $known[$row['ref']]['hash'] === $hash && (int)$known[$row['ref']]['is_active'] === 1) {
				$same[] = $row['ref'];
			} else {
				$row['hash'] = $hash;
				$changed[] = $row;
			}
		}

		if ($same) {
			$this->db->exec("UPDATE `" . $table . "` SET run_id = " . $run . " WHERE ref IN (" . $this->in($same) . ")");
		}

		if ($changed) {
			$columns = self::$columns[$entity];
			$values = array();

			foreach ($changed as $row) {
				$cells = array();

				foreach ($columns as $column) {
					$cells[] = $this->quote($row[$column]);
				}

				$values[] = '(' . implode(',', $cells) . ',1,' . $run . ",'" . $row['hash'] . "')";
			}

			$this->db->exec("REPLACE INTO `" . $table . "` (`" . implode('`,`', $columns) . "`, is_active, run_id, hash) VALUES " . implode(',', $values));
		}
	}

	private function save(array $s) {
		$this->db->exec("REPLACE INTO `" . $this->prefix . "ocs_np_sync` (entity, run_id, page, status, started_at, finished_at, rows_run, rows_total, error) VALUES ("
			. $this->quote($s['entity']) . ',' . (int)$s['run_id'] . ',' . (int)$s['page'] . ',' . $this->quote($s['status']) . ',' . (int)$s['started_at'] . ',' . (int)$s['finished_at'] . ',' . (int)$s['rows_run'] . ',' . (int)$s['rows_total'] . ',' . $this->quote($s['error']) . ')');
	}

	private function in(array $values) {
		return implode(',', array_map(array($this, 'quote'), $values));
	}

	private function quote($value) {
		return is_int($value) ? (string)$value : "'" . $this->db->escape((string)$value) . "'";
	}

	private function check($entity) {
		if (!isset(self::$tables[$entity])) {
			throw new \InvalidArgumentException('Unknown entity ' . $entity);
		}
	}
}
