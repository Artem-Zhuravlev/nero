<?php
// Extension: ocs_menu. Category rows with per-category extras, cached per language and store. Owner: SPEC-027 (REQ-MNU-030).

class ModelExtensionOcsMenuTree extends Model {
	const TTL_KEY = 'ocs_menu.rows.';

	/**
	 * One query, cached (cache TTL of the store); returns flat rows for \Ocs\CategoryTree::build().
	 */
	public function getRows() {
		$key = self::TTL_KEY . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id');

		$rows = $this->cache->get($key);

		if (!is_array($rows)) {
			$query = $this->db->query("SELECT c.category_id, c.parent_id, c.image, c.sort_order, c.`top`, c.`column`, cd.name, x.icon, x.popular, x.banner_image, x.banner_url FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id) LEFT JOIN " . DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id) LEFT JOIN " . DB_PREFIX . "ocs_menu_category x ON (x.category_id = c.category_id) WHERE cd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND c2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND c.status = '1' ORDER BY c.sort_order, LCASE(cd.name)");

			$rows = $query->rows;

			$this->cache->set($key, $rows);
		}

		return $rows;
	}

	/**
	 * Manufacturers of products in each given category including its descendants (one cached query).
	 *
	 * @param int[] $category_ids
	 * @return array category_id => array(array(manufacturer_id, name), ...)
	 */
	public function getBrands($category_ids, $limit) {
		$ids = array();

		foreach ((array)$category_ids as $id) {
			$ids[] = (int)$id;
		}

		if (!$ids) {
			return array();
		}

		sort($ids);

		$key = 'ocs_menu.brands.' . (int)$this->config->get('config_store_id') . '.' . md5(implode(',', $ids));

		$rows = $this->cache->get($key);

		if (!is_array($rows)) {
			$query = $this->db->query("SELECT cp.path_id AS category_id, m.manufacturer_id, m.name, COUNT(*) AS cnt FROM " . DB_PREFIX . "category_path cp INNER JOIN " . DB_PREFIX . "product_to_category p2c ON (p2c.category_id = cp.category_id) INNER JOIN " . DB_PREFIX . "product p ON (p.product_id = p2c.product_id) INNER JOIN " . DB_PREFIX . "product_to_store p2s ON (p2s.product_id = p.product_id) INNER JOIN " . DB_PREFIX . "manufacturer m ON (m.manufacturer_id = p.manufacturer_id) INNER JOIN " . DB_PREFIX . "manufacturer_to_store m2s ON (m2s.manufacturer_id = m.manufacturer_id) WHERE cp.path_id IN (" . implode(',', $ids) . ") AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND m2s.store_id = '" . (int)$this->config->get('config_store_id') . "' GROUP BY cp.path_id, m.manufacturer_id");

			$rows = $query->rows;

			$this->cache->set($key, $rows);
		}

		return \Ocs\CategoryTree::groupBrands($rows, $limit);
	}

	/**
	 * Decorated tree (path and href on every node).
	 */
	public function getTree($levels) {
		$tree = \Ocs\CategoryTree::build($this->getRows(), $levels);

		return \Ocs\CategoryTree::decorate($tree, function ($path) {
			return $this->url->link('product/category', 'path=' . $path);
		});
	}
}
