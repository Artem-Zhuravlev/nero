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
	 * All manufacturers of the current store (one cached query), sort_order then name.
	 *
	 * @return array of array(manufacturer_id, name)
	 */
	public function getAllBrands() {
		$key = 'ocs_menu.brands.' . (int)$this->config->get('config_store_id');

		$rows = $this->cache->get($key);

		if (!is_array($rows)) {
			$query = $this->db->query("SELECT m.manufacturer_id, m.name FROM " . DB_PREFIX . "manufacturer m INNER JOIN " . DB_PREFIX . "manufacturer_to_store m2s ON (m2s.manufacturer_id = m.manufacturer_id) WHERE m2s.store_id = '" . (int)$this->config->get('config_store_id') . "' ORDER BY m.sort_order, LCASE(m.name)");

			$rows = $query->rows;

			$this->cache->set($key, $rows);
		}

		return $rows;
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
