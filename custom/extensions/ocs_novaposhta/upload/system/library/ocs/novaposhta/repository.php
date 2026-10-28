<?php
// Extension: ocs_novaposhta. Read side over the local directories: city and branch suggestions, point checks, zone matching. Owner: SPEC-085.
// Every query is local and indexed; the Nova Poshta API is never called from the storefront (REQ-NP-050).

namespace Ocs\Novaposhta;

class Repository {
	private $db;
	private $prefix;

	public function __construct(Db $db, $prefix) {
		$this->db = $db;
		$this->prefix = $prefix;
	}

	/**
	 * City suggestions by name prefix (Ukrainian or Russian), larger cities first.
	 *
	 * @param string $lang 'ru' for Russian names, anything else = Ukrainian
	 * @return array [ref, name, area, type, points]
	 */
	public function cities($query, $lang = 'uk', $limit = 10) {
		$q = Text::norm(mb_substr((string)$query, 0, 64, 'UTF-8'));

		if (mb_strlen($q, 'UTF-8') < 2) {
			return array();
		}

		$e = $this->db->escape($q);
		$ru = $lang == 'ru';

		$sql = "SELECT c.ref, c.name_uk, c.name_ru, c.type_uk, c.type_ru, c.warehouses_count, a.name_uk AS area_uk, a.name_ru AS area_ru
			FROM `" . $this->prefix . "ocs_np_city` c LEFT JOIN `" . $this->prefix . "ocs_np_area` a ON a.ref = c.area_ref
			WHERE c.is_active = 1 AND (c.search_uk LIKE '" . $e . "%' OR c.search_ru LIKE '" . $e . "%')
			ORDER BY c.warehouses_count DESC, c.name_uk ASC LIMIT " . max(1, min(20, (int)$limit));

		$out = array();

		foreach ($this->db->rows($sql) as $r) {
			$out[] = array(
				'ref'    => $r['ref'],
				'name'   => $ru ? $r['name_ru'] : $r['name_uk'],
				'type'   => $ru ? $r['type_ru'] : $r['type_uk'],
				'area'   => $ru ? $r['area_ru'] : $r['area_uk'],
				'points' => (int)$r['warehouses_count']
			);
		}

		return $out;
	}

	/**
	 * Points of one city. Every word of the query must be found (number, street, name); an exact number comes first.
	 *
	 * @param string $type all|branch|postomat
	 * @return array total, items [ref, number, type, name, schedule, max_weight]
	 */
	public function warehouses($cityRef, $query, $type = 'all', $page = 1, $perPage = 50, $lang = 'uk') {
		$where = "city_ref = '" . $this->db->escape($cityRef) . "' AND is_active = 1 AND " . ($type == 'branch' || $type == 'postomat' ? "type = '" . $type . "'" : "type <> 'cargo'");

		$q = Text::norm(mb_substr((string)$query, 0, 64, 'UTF-8'));
		$exact = '';

		foreach (array_slice(array_filter(explode(' ', $q), 'strlen'), 0, 5) as $word) {
			$where .= " AND search LIKE '%" . $this->db->escape($word) . "%'";

			if ($exact === '' && ctype_digit($word)) {
				$exact = (int)$word;
			}
		}

		$table = "`" . $this->prefix . "ocs_np_warehouse`";
		$total = (int)$this->db->rows("SELECT COUNT(*) AS c FROM " . $table . " WHERE " . $where)[0]['c'];

		$perPage = max(1, min(100, (int)$perPage));
		$offset = (max(1, (int)$page) - 1) * $perPage;
		$order = ($exact !== '' ? "(number = " . $exact . ") DESC, " : '') . "number ASC";

		$items = array();

		foreach ($this->db->rows("SELECT ref, number, type, name_uk, name_ru, schedule, max_weight FROM " . $table . " WHERE " . $where . " ORDER BY " . $order . " LIMIT " . $perPage . " OFFSET " . $offset) as $r) {
			$items[] = array(
				'ref'        => $r['ref'],
				'number'     => (int)$r['number'],
				'type'       => $r['type'],
				'name'       => $lang == 'ru' ? $r['name_ru'] : $r['name_uk'],
				'schedule'   => self::today($r['schedule']),
				'max_weight' => (int)$r['max_weight']
			);
		}

		return array('total' => $total, 'items' => $items);
	}

	/**
	 * A point the customer may choose: it must exist, be active and belong to the given city (REQ-NP-021, 032).
	 *
	 * @return array|null city_ref, city_name, area_name, warehouse_ref, type, number, name (both languages)
	 */
	public function point($cityRef, $warehouseRef) {
		$rows = $this->db->rows("SELECT w.ref, w.city_ref, w.type, w.number, w.name_uk, w.name_ru, c.name_uk AS city_uk, c.name_ru AS city_ru, a.name_uk AS area_uk, a.name_ru AS area_ru
			FROM `" . $this->prefix . "ocs_np_warehouse` w JOIN `" . $this->prefix . "ocs_np_city` c ON c.ref = w.city_ref LEFT JOIN `" . $this->prefix . "ocs_np_area` a ON a.ref = c.area_ref
			WHERE w.ref = '" . $this->db->escape($warehouseRef) . "' AND w.city_ref = '" . $this->db->escape($cityRef) . "' AND w.is_active = 1 AND w.type <> 'cargo' AND c.is_active = 1");

		if (!$rows) {
			return null;
		}

		$r = $rows[0];

		return array(
			'city_ref'      => $r['city_ref'],
			'warehouse_ref' => $r['ref'],
			'type'          => $r['type'],
			'number'        => (int)$r['number'],
			'name_uk'       => $r['name_uk'],
			'name_ru'       => $r['name_ru'],
			'city_uk'       => $r['city_uk'],
			'city_ru'       => $r['city_ru'],
			'area_uk'       => (string)$r['area_uk'],
			'area_ru'       => (string)$r['area_ru']
		);
	}

	/** A city on its own (without a point), for the city step. */
	public function city($cityRef) {
		$rows = $this->db->rows("SELECT c.ref, c.name_uk, c.name_ru, a.name_uk AS area_uk, a.name_ru AS area_ru
			FROM `" . $this->prefix . "ocs_np_city` c LEFT JOIN `" . $this->prefix . "ocs_np_area` a ON a.ref = c.area_ref
			WHERE c.ref = '" . $this->db->escape($cityRef) . "' AND c.is_active = 1");

		return $rows ? array('city_ref' => $rows[0]['ref'], 'city_uk' => $rows[0]['name_uk'], 'city_ru' => $rows[0]['name_ru'], 'area_uk' => (string)$rows[0]['area_uk'], 'area_ru' => (string)$rows[0]['area_ru']) : null;
	}

	/**
	 * Best-effort match of a Nova Poshta area ("Київська") to an OpenCart zone of the country ("Київська область").
	 *
	 * @param array $zones rows of [zone_id, name]
	 * @return int zone_id or 0
	 */
	public static function matchZone($area, array $zones) {
		$strip = function ($s) {
			$s = mb_strtolower((string)$s, 'UTF-8');
			$s = preg_replace('/(область|обл\.?|oblast|region|область)/u', '', $s);

			return trim(preg_replace('/[^\p{L}]+/u', '', $s));
		};

		$a = $strip($area);

		if ($a === '') {
			return 0;
		}

		foreach ($zones as $z) {
			$n = $strip($z['name']);

			if ($n !== '' && ($n === $a || mb_substr($n, 0, 6, 'UTF-8') === mb_substr($a, 0, 6, 'UTF-8'))) {
				return (int)$z['zone_id'];
			}
		}

		return 0;
	}

	/** Compact working hours for today, or an empty string. */
	private static function today($json) {
		$s = json_decode((string)$json, true);

		if (!is_array($s)) {
			return '';
		}

		$days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
		$day = $days[(int)date('N') - 1];

		return isset($s[$day]) && is_string($s[$day]) && $s[$day] !== '-' ? $s[$day] : '';
	}
}
