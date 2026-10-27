<?php
// Extension: ocs_super_search. Storefront events: product search results (search page and the AJAX suggestions) come from the token index.
// Owner: SPEC-079 (REQ-SSR-001, 008, 010). A before event that returns a non-empty value replaces the core model call; returning nothing
// leaves core search in charge (module off, index not built, unsupported filter, nothing found). Handlers never throw (REQ-EXT-062).

class ControllerExtensionOcsSuperSearchEvents extends Controller {
	/**
	 * catalog/model/catalog/product/getProducts/before: $args = array(data)
	 */
	public function beforeGetProducts(&$route, &$args) {
		$ids = $this->ranked($args);

		if (!$ids) {
			return null;
		}

		$data = $args[0];
		$start = isset($data['start']) ? max(0, (int)$data['start']) : 0;
		$limit = isset($data['limit']) ? max(1, (int)$data['limit']) : 20;

		$products = array();

		try {
			foreach (array_slice($ids, $start, $limit) as $product_id) {
				$product = $this->model_catalog_product->getProduct($product_id);

				if ($product) {
					$products[$product_id] = $product;
				}
			}
		} catch (\Exception $e) {
			$this->log->write('[ocs_super_search] ' . $e->getMessage());

			return null;
		}

		// an empty page (start beyond the list) must not fall back to the core query
		return $products ? $products : null;
	}

	/**
	 * catalog/model/catalog/product/getTotalProducts/before: $args = array(data)
	 */
	public function beforeGetTotalProducts(&$route, &$args) {
		$ids = $this->ranked($args);

		return $ids ? count($ids) : null;
	}

	/**
	 * Ranked, filtered, sorted ids for a search-page style $data, or array() when core must answer.
	 */
	private function ranked($args) {
		if (!$this->config->get('module_ocs_super_search_status') || !$this->config->get('module_ocs_super_search_built_at') || !is_array($args) || !isset($args[0]) || !is_array($args[0])) {
			return array();
		}

		$data = $args[0];
		$name = isset($data['filter_name']) ? trim((string)$data['filter_name']) : '';

		// the index covers names, models, tags: description search, other tag text or attribute filters stay with core
		if ($name === '' || !empty($data['filter_description']) || !empty($data['filter_filter']) || (!empty($data['filter_tag']) && $data['filter_tag'] !== $data['filter_name'])) {
			return array();
		}

		if (mb_strlen($name, 'UTF-8') < max(1, (int)$this->config->get('module_ocs_super_search_min_length'))) {
			return array();
		}

		try {
			$this->load->model('catalog/product');

			$index = new \Ocs\SuperSearchIndex($this->db, DB_PREFIX, $this->cache);

			$ids = $index->search($name, array(
				'store_id'   => (int)$this->config->get('config_store_id'),
				'generation' => (string)$this->config->get('module_ocs_super_search_generation'),
				'ttl'        => (int)$this->config->get('module_ocs_super_search_cache_ttl'),
				'layout'     => $this->config->get('module_ocs_super_search_layout'),
				'translit'   => $this->config->get('module_ocs_super_search_translit'),
				'typo'       => $this->config->get('module_ocs_super_search_typo'),
				'synonyms'   => (string)$this->config->get('module_ocs_super_search_synonyms')
			));

			return $index->arrange($ids, array(
				'category_id'       => isset($data['filter_category_id']) ? (int)$data['filter_category_id'] : 0,
				'sub_category'      => !empty($data['filter_sub_category']),
				'manufacturer_id'   => isset($data['filter_manufacturer_id']) ? (int)$data['filter_manufacturer_id'] : 0,
				'sort'              => isset($data['sort']) ? $data['sort'] : 'p.sort_order',
				'order'             => isset($data['order']) ? $data['order'] : 'ASC',
				'language_id'       => (int)$this->config->get('config_language_id'),
				'customer_group_id' => (int)$this->config->get('config_customer_group_id')
			));
		} catch (\Exception $e) {
			$this->log->write('[ocs_super_search] ' . $e->getMessage());

			return array();
		}
	}
}
