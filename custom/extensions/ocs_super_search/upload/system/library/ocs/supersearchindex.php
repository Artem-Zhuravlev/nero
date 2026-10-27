<?php
// Extension: ocs_super_search. Token index of products (all languages in one table), vocabulary for typo/phonetic expansion and the
// query itself. Owner: SPEC-079 (REQ-SSR-001, 005, 006, 008, 020..023).
//
// $db is a core DB object (query/escape/countAffected), $cache a core Cache object (get/set) or null. Table names get $prefix.
// Everything used in SQL besides numbers is a letter/digit token, escaped anyway.

namespace Ocs;

class SuperSearchIndex {
	const MAX_TOKENS = 200;
	const CANDIDATES = 200;
	const TERMS_PER_WORD = 40;
	const SHARD_LIMIT = 20000;

	// tier scores: higher = better match; field weight (1..10) is added
	const T_EXACT = 500, T_PREFIX = 450, T_LAYOUT = 400, T_LAYOUT_PREFIX = 350, T_SYNONYM = 300, T_PHONETIC = 200, T_TYPO = 150;

	private $db;
	private $prefix;
	private $cache;

	public function __construct($db, $prefix, $cache = null) {
		$this->db = $db;
		$this->prefix = $prefix;
		$this->cache = $cache;
	}

	/* ----------------------------------------------------------------- indexing */

	/**
	 * Tokens of products: array(product_id => array(token => weight)). Products that do not exist get no entry.
	 */
	public function collect(array $ids) {
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
		$out = array();

		if (!$ids) {
			return $out;
		}

		$in = implode(',', $ids);

		$add = function ($product_id, $text, $weight, $compact = false) use (&$out) {
			if (!isset($out[$product_id])) {
				return; // rows of products that no longer exist are not indexed
			}

			foreach (SuperSearch::tokens($text) as $token) {
				$token = mb_substr($token, 0, 48, 'UTF-8');

				if (!isset($out[$product_id][$token]) || $out[$product_id][$token] < $weight) {
					$out[$product_id][$token] = $weight;
				}
			}

			if ($compact) {
				// "MBA13-M5" is also searchable as "mba13m5"
				$joined = implode('', SuperSearch::tokens($text));

				if ($joined !== '' && mb_strlen($joined, 'UTF-8') <= 48 && !isset($out[$product_id][$joined])) {
					$out[$product_id][$joined] = $weight;
				}
			}
		};

		$p = $this->prefix;

		foreach ($this->db->query("SELECT p.product_id, p.model, p.sku, p.mpn, m.name AS brand FROM " . $p . "product p LEFT JOIN " . $p . "manufacturer m ON (m.manufacturer_id = p.manufacturer_id) WHERE p.product_id IN (" . $in . ")")->rows as $row) {
			$out[(int)$row['product_id']] = array();

			$add((int)$row['product_id'], $row['model'], 8, true);
			$add((int)$row['product_id'], $row['sku'], 8, true);
			$add((int)$row['product_id'], $row['mpn'], 7, true);
			$add((int)$row['product_id'], (string)$row['brand'], 6);
		}

		foreach ($this->db->query("SELECT product_id, name, tag, meta_keyword FROM " . $p . "product_description WHERE product_id IN (" . $in . ")")->rows as $row) {
			$add((int)$row['product_id'], $row['name'], 10);
			$add((int)$row['product_id'], $row['tag'], 6);
			$add((int)$row['product_id'], $row['meta_keyword'], 5);
		}

		foreach ($this->db->query("SELECT p2c.product_id, cd.name FROM " . $p . "product_to_category p2c INNER JOIN " . $p . "category_description cd ON (cd.category_id = p2c.category_id) WHERE p2c.product_id IN (" . $in . ")")->rows as $row) {
			$add((int)$row['product_id'], $row['name'], 4);
		}

		foreach ($out as $product_id => $tokens) {
			if (count($tokens) > self::MAX_TOKENS) {
				arsort($tokens);

				$out[$product_id] = array_slice($tokens, 0, self::MAX_TOKENS, true);
			}
		}

		return $out;
	}

	/**
	 * Rebuild the index rows of the given products (also used for deleted products: their rows are just removed) and keep the
	 * vocabulary in step. The caller bumps the generation (touch()).
	 */
	public function reindex(array $ids) {
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

		if (!$ids) {
			return;
		}

		$p = $this->prefix;
		$in = implode(',', $ids);

		$affected = array();

		foreach ($this->db->query("SELECT DISTINCT token FROM " . $p . "ocs_search_token WHERE product_id IN (" . $in . ")")->rows as $row) {
			$affected[$row['token']] = true;
		}

		$this->db->query("DELETE FROM " . $p . "ocs_search_token WHERE product_id IN (" . $in . ")");

		$rows = array();

		foreach ($this->collect($ids) as $product_id => $tokens) {
			foreach ($tokens as $token => $weight) {
				$token = (string)$token;
				$affected[$token] = true;
				$rows[] = "('" . $this->db->escape($token) . "', '" . (int)$product_id . "', '" . (int)$weight . "')";
			}
		}

		foreach (array_chunk($rows, 500) as $chunk) {
			$this->db->query("REPLACE INTO " . $p . "ocs_search_token (token, product_id, weight) VALUES " . implode(', ', $chunk));
		}

		$this->syncVocabulary(array_map('strval', array_keys($affected)));
	}

	/**
	 * Vocabulary rows (letters-only terms of 3+ characters) follow the document frequency of the tokens.
	 */
	public function syncVocabulary(array $tokens) {
		$p = $this->prefix;

		foreach (array_chunk($tokens, 300) as $chunk) {
			$list = array();

			foreach ($chunk as $token) {
				$list[] = "'" . $this->db->escape($token) . "'";
			}

			$df = array();

			foreach ($this->db->query("SELECT token, COUNT(*) AS df FROM " . $p . "ocs_search_token WHERE token IN (" . implode(',', $list) . ") GROUP BY token")->rows as $row) {
				$df[mb_strtolower((string)$row['token'], 'UTF-8')] = (int)$row['df'];
			}

			$rows = array();
			$gone = array();

			foreach ($chunk as $token) {
				$count = isset($df[mb_strtolower((string)$token, 'UTF-8')]) ? $df[mb_strtolower((string)$token, 'UTF-8')] : 0;
				$letters = preg_match('/^\p{L}{3,48}$/u', (string)$token) === 1;

				if ($count > 0 && $letters) {
					$rows[] = "('" . $this->db->escape($token) . "', '" . $this->db->escape(SuperSearch::phonetic($token)) . "', '" . mb_strlen($token, 'UTF-8') . "', '" . $count . "')";
				} else {
					$gone[] = "'" . $this->db->escape($token) . "'";
				}
			}

			if ($rows) {
				$this->db->query("REPLACE INTO " . $p . "ocs_search_vocab (term, phon, len, df) VALUES " . implode(', ', $rows));
			}

			if ($gone) {
				$this->db->query("DELETE FROM " . $p . "ocs_search_vocab WHERE term IN (" . implode(',', $gone) . ")");
			}
		}
	}

	public function clear() {
		$this->db->query("DELETE FROM " . $this->prefix . "ocs_search_token");
		$this->db->query("DELETE FROM " . $this->prefix . "ocs_search_vocab");
	}

	/**
	 * Product ids after $after, ascending (batches of the full rebuild).
	 */
	public function nextIds($after, $limit) {
		$ids = array();

		foreach ($this->db->query("SELECT product_id FROM " . $this->prefix . "product WHERE product_id > '" . (int)$after . "' ORDER BY product_id ASC LIMIT " . (int)$limit)->rows as $row) {
			$ids[] = (int)$row['product_id'];
		}

		return $ids;
	}

	public function countProducts() {
		return (int)$this->db->query("SELECT COUNT(*) AS total FROM " . $this->prefix . "product")->row['total'];
	}

	public function countTokens() {
		return (int)$this->db->query("SELECT COUNT(*) AS total FROM " . $this->prefix . "ocs_search_token")->row['total'];
	}

	/**
	 * Store a setting of this module (new value invalidates caches when it is the generation).
	 */
	public function setSetting($key, $value) {
		$p = $this->prefix;
		$key = $this->db->escape($key);

		$this->db->query("UPDATE " . $p . "setting SET `value` = '" . $this->db->escape((string)$value) . "', serialized = '0' WHERE store_id = '0' AND `code` = 'module_ocs_super_search' AND `key` = '" . $key . "'");

		if (!$this->db->countAffected()) {
			$exists = $this->db->query("SELECT setting_id FROM " . $p . "setting WHERE store_id = '0' AND `code` = 'module_ocs_super_search' AND `key` = '" . $key . "'")->num_rows;

			if (!$exists) {
				$this->db->query("INSERT INTO " . $p . "setting SET store_id = '0', `code` = 'module_ocs_super_search', `key` = '" . $key . "', `value` = '" . $this->db->escape((string)$value) . "', serialized = '0'");
			}
		}
	}

	public function touch() {
		$this->setSetting('module_ocs_super_search_generation', (string)(int)(microtime(true) * 1000));
	}

	/* ----------------------------------------------------------------- query */

	/**
	 * Ranked product ids (best first, at most CANDIDATES) of enabled products of the store.
	 *
	 * $o: store_id, generation, ttl (seconds), layout, translit, typo, synonyms (textarea text) - the last four are on/off or text.
	 */
	public function search($query, array $o) {
		$text = mb_substr(trim((string)$query), 0, SuperSearch::MAX_QUERY, 'UTF-8');
		$words = array_slice(SuperSearch::tokens($text), 0, SuperSearch::MAX_WORDS);

		if (!$words) {
			return array();
		}

		$gen = isset($o['generation']) ? (string)$o['generation'] : '0';
		$key = 'ocs_ss.' . $gen . '.q.' . md5((int)$o['store_id'] . '|' . implode(' ', $words) . '|' . (empty($o['layout']) ? 0 : 1) . (empty($o['translit']) ? 0 : 1) . (empty($o['typo']) ? 0 : 1) . '|' . (empty($o['synonyms']) ? '' : md5((string)$o['synonyms'])));
		$ttl = isset($o['ttl']) ? max(0, (int)$o['ttl']) : 300;

		$hit = $this->cache ? $this->cache->get($key) : false;

		if (is_array($hit) && isset($hit['t'], $hit['ids']) && $hit['t'] + $ttl >= time()) {
			return $hit['ids'];
		}

		$synonyms = !empty($o['synonyms']) ? SuperSearch::parseSynonyms($o['synonyms']) : array('groups' => array());
		$slots = SuperSearch::slots($words, $synonyms['groups']);

		$layouts = array();

		if (!empty($o['layout'])) {
			foreach (SuperSearch::layoutVariants($text) as $variant) {
				$v = array_slice(SuperSearch::tokens($variant), 0, SuperSearch::MAX_WORDS);

				if (count($v) === count($words)) {
					$layouts[] = $v;
				}
			}
		}

		$plan = array();
		$pos = 0;
		$last = count($slots) - 1;

		foreach ($slots as $n => $slot) {
			$terms = array();
			$prefixes = array();
			$bases = array();
			$single = count($slot['words']) === 1;

			foreach ($slot['words'] as $w) {
				$terms[$w] = self::T_EXACT;
				$bases[$w] = true;
			}

			if ($single && $n === $last) {
				$prefixes[$slot['words'][0]] = self::T_PREFIX;
			}

			foreach ($slot['syn'] as $w) {
				$terms[$w] = max(isset($terms[$w]) ? $terms[$w] : 0, self::T_SYNONYM);
			}

			if ($single) {
				foreach ($layouts as $v) {
					$w = $v[$pos];

					if (!isset($terms[$w])) {
						$terms[$w] = self::T_LAYOUT;
					}

					$bases[$w] = true;

					if ($n === $last && !isset($prefixes[$w])) {
						$prefixes[$w] = self::T_LAYOUT_PREFIX;
					}
				}
			}

			foreach (array_keys($bases) as $w) {
				foreach ($this->expand($w, $gen, $o) as $term => $tier) {
					if (!isset($terms[$term]) || $terms[$term] < $tier) {
						$terms[$term] = $tier;
					}
				}
			}

			$plan[] = array('terms' => $terms, 'prefixes' => $prefixes);
			$pos += count($slot['words']);
		}

		$ids = $this->fetch($plan, (int)$o['store_id']);

		if ($this->cache) {
			$this->cache->set($key, array('t' => time(), 'ids' => $ids));
		}

		return $ids;
	}

	/**
	 * Vocabulary terms that a word may have meant: same phonetic key, or one edit away. Letters-only words only.
	 *
	 * @return array term => tier score
	 */
	private function expand($word, $gen, array $o) {
		$out = array();
		$len = mb_strlen($word, 'UTF-8');

		if (preg_match('/^\p{L}+$/u', $word) !== 1) {
			return $out;
		}

		$p = $this->prefix;

		if (!empty($o['translit']) && $len >= 3) {
			$phon = SuperSearch::phonetic($word);

			if (strlen($phon) >= 3) {
				$key = 'ocs_ss.' . $gen . '.p.' . md5($phon);
				$list = $this->cache ? $this->cache->get($key) : false;

				if (!is_array($list)) {
					$list = array();

					foreach ($this->db->query("SELECT term FROM " . $p . "ocs_search_vocab WHERE phon = '" . $this->db->escape($phon) . "' ORDER BY df DESC LIMIT " . self::TERMS_PER_WORD)->rows as $row) {
						$list[] = $row['term'];
					}

					if ($this->cache) {
						$this->cache->set($key, $list);
					}
				}

				foreach ($list as $term) {
					$out[$term] = self::T_PHONETIC;
				}
			}
		}

		if (!empty($o['typo']) && $len >= 4) {
			$found = 0;

			foreach (array($len, $len - 1, $len + 1) as $l) {
				foreach ($this->shard($l, $gen) as $term) {
					if ($term !== $word && SuperSearch::withinOneEdit($word, $term)) {
						if (!isset($out[$term])) {
							$out[$term] = self::T_TYPO;
						}

						if (++$found >= self::TERMS_PER_WORD) {
							break 2;
						}
					}
				}
			}
		}

		return $out;
	}

	/**
	 * Vocabulary terms of one length, most common first (cached per generation).
	 */
	private function shard($len, $gen) {
		$key = 'ocs_ss.' . $gen . '.l.' . (int)$len;
		$list = $this->cache ? $this->cache->get($key) : false;

		if (!is_array($list)) {
			$list = array();

			foreach ($this->db->query("SELECT term FROM " . $this->prefix . "ocs_search_vocab WHERE len = '" . (int)$len . "' ORDER BY df DESC LIMIT " . self::SHARD_LIMIT)->rows as $row) {
				$list[] = $row['term'];
			}

			if ($this->cache) {
				$this->cache->set($key, $list);
			}
		}

		return $list;
	}

	/**
	 * ONE query: rows of the expanded tokens, grouped per product; every slot must be matched (AND), best tier + field weight
	 * of each slot is summed.
	 */
	private function fetch(array $plan, $store_id) {
		$p = $this->prefix;
		$all = array();
		$like = array();
		$selects = array();
		$having = array();
		$sum = array();

		foreach ($plan as $i => $slot) {
			$byTier = array();

			foreach ($slot['terms'] as $term => $tier) {
				$byTier[$tier][] = "'" . $this->db->escape((string)$term) . "'";
				$all[(string)$term] = true;
			}

			$when = array();

			foreach ($byTier as $tier => $list) {
				$when[$tier][] = 't.token IN (' . implode(',', $list) . ')';
			}

			foreach ($slot['prefixes'] as $prefix => $tier) {
				$cond = "t.token LIKE '" . $this->db->escape((string)$prefix) . "%'";
				$when[$tier][] = $cond;
				$like[$cond] = true;
			}

			krsort($when);

			$case = '';

			foreach ($when as $tier => $conds) {
				$case .= ' WHEN ' . implode(' OR ', $conds) . ' THEN ' . (int)$tier;
			}

			$selects[] = 'MAX((CASE' . $case . ' END) + t.weight) AS w' . $i;
			$having[] = 'w' . $i . ' IS NOT NULL';
			$sum[] = 'w' . $i;
		}

		$where = array();

		if ($all) {
			$where[] = "t.token IN ('" . implode("','", array_map(array($this->db, 'escape'), array_map('strval', array_keys($all)))) . "')";
		}

		foreach (array_keys($like) as $cond) {
			$where[] = $cond;
		}

		$sql = "SELECT t.product_id, " . implode(', ', $selects) . " FROM " . $p . "ocs_search_token t"
			. " INNER JOIN " . $p . "product p ON (p.product_id = t.product_id)"
			. " INNER JOIN " . $p . "product_to_store p2s ON (p2s.product_id = t.product_id)"
			. " WHERE (" . implode(' OR ', $where) . ") AND p.status = '1' AND p.date_available <= CURRENT_DATE AND p2s.store_id = '" . (int)$store_id . "'"
			. " GROUP BY t.product_id HAVING " . implode(' AND ', $having)
			. " ORDER BY (" . implode(' + ', $sum) . ") DESC, t.product_id ASC LIMIT " . self::CANDIDATES;

		$ids = array();

		foreach ($this->db->query($sql)->rows as $row) {
			$ids[] = (int)$row['product_id'];
		}

		return $ids;
	}

	/**
	 * Apply the search page filters and sort order to ranked ids. Without category/brand filter and with the default sort the
	 * relevance order is kept and no query is made.
	 *
	 * $f: category_id, sub_category, manufacturer_id, sort, order, language_id, customer_group_id
	 */
	public function arrange(array $ids, array $f) {
		if (!$ids) {
			return array();
		}

		$sort = isset($f['sort']) ? $f['sort'] : 'p.sort_order';
		$allowed = array('pd.name', 'p.model', 'p.price', 'rating', 'p.quantity', 'p.date_added');

		$filtered = !empty($f['category_id']) || !empty($f['manufacturer_id']);

		if (!$filtered && !in_array($sort, $allowed, true)) {
			return $ids;
		}

		$p = $this->prefix;
		$in = implode(',', array_map('intval', $ids));

		$join = '';
		$where = '';

		if (!empty($f['category_id'])) {
			if (!empty($f['sub_category'])) {
				$join .= " INNER JOIN " . $p . "category_path cp ON (cp.category_id = p2c.category_id)";
				$where .= " AND cp.path_id = '" . (int)$f['category_id'] . "'";
			} else {
				$where .= " AND p2c.category_id = '" . (int)$f['category_id'] . "'";
			}

			$join = " INNER JOIN " . $p . "product_to_category p2c ON (p2c.product_id = p.product_id)" . $join;
		}

		if (!empty($f['manufacturer_id'])) {
			$where .= " AND p.manufacturer_id = '" . (int)$f['manufacturer_id'] . "'";
		}

		$order = (isset($f['order']) && $f['order'] === 'DESC') ? 'DESC' : 'ASC';

		$by = '';

		if ($sort === 'pd.name') {
			$join .= " LEFT JOIN " . $p . "product_description pd ON (pd.product_id = p.product_id AND pd.language_id = '" . (int)$f['language_id'] . "')";
			$by = 'LCASE(pd.name)';
		} elseif ($sort === 'p.price') {
			$by = "COALESCE((SELECT ps.price FROM " . $p . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$f['customer_group_id'] . "' AND (ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW()) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1), p.price)";
		} elseif ($sort === 'rating') {
			$by = "(SELECT AVG(r.rating) FROM " . $p . "review r WHERE r.product_id = p.product_id AND r.status = '1')";
		} elseif (in_array($sort, $allowed, true)) {
			$by = $sort;
		}

		$rows = $this->db->query("SELECT DISTINCT p.product_id" . ($by ? ', ' . $by . ' AS sort_key' : '') . " FROM " . $p . "product p" . $join . " WHERE p.product_id IN (" . $in . ")" . $where . ($by ? " ORDER BY sort_key " . $order . ", p.product_id ASC" : ''))->rows;

		$found = array();

		foreach ($rows as $row) {
			$found[] = (int)$row['product_id'];
		}

		if ($by) {
			return $found;
		}

		return array_values(array_intersect($ids, $found));
	}
}
