<?php
// Extension: ocs_bookmarks. Compare categories of the compared products: two batched queries, no per-product lookups. Owner: SPEC-057 (REQ-CMP-001, REQ-CMP-010).

class ModelExtensionOcsBookmarksCompare extends Model {
	/**
	 * @param array $ids product ids
	 * @return array product_id => compare category id (0 = no category); disabled or missing products are not in the result
	 */
	public function categories(array $ids) {
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
		$result = array();

		if (!$ids) {
			return $result;
		}

		$query = $this->db->query("SELECT p.product_id, p2c.category_id, (SELECT MAX(cp.level) FROM " . DB_PREFIX . "category_path cp WHERE cp.category_id = p2c.category_id) AS level FROM " . DB_PREFIX . "product p INNER JOIN " . DB_PREFIX . "product_to_store p2s ON (p2s.product_id = p.product_id AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "') LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (p2c.product_id = p.product_id) WHERE p.product_id IN (" . implode(',', $ids) . ") AND p.status = '1' AND p.date_available <= NOW()");

		$rows = array();

		foreach ($query->rows as $row) {
			$rows[(int)$row['product_id']][] = array('category_id' => $row['category_id'], 'level' => $row['level']);
		}

		foreach ($rows as $product_id => $list) {
			$result[$product_id] = \Ocs\Bookmarks::deepest($list);
		}

		return $result;
	}

	/**
	 * @param array $ids category ids
	 * @return array category_id => name in the current language
	 */
	public function names(array $ids) {
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
		$result = array();

		if (!$ids) {
			return $result;
		}

		$query = $this->db->query("SELECT category_id, name FROM " . DB_PREFIX . "category_description WHERE category_id IN (" . implode(',', $ids) . ") AND language_id = '" . (int)$this->config->get('config_language_id') . "'");

		foreach ($query->rows as $row) {
			$result[(int)$row['category_id']] = html_entity_decode($row['name'], ENT_QUOTES, 'UTF-8');
		}

		return $result;
	}
}
