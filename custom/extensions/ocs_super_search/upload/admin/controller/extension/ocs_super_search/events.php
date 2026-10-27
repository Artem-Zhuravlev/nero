<?php
// Extension: ocs_super_search. Admin events: the token index follows product saves and deletes. Owner: SPEC-079 (REQ-SSR-022).
// Active once the index has been built. Handlers never throw to the admin user (REQ-EXT-062).

class ControllerExtensionOcsSuperSearchEvents extends Controller {
	/**
	 * admin/model/catalog/product/addProduct/after: $output = new product_id
	 */
	public function afterProductAdd(&$route, &$args, &$output) {
		$this->update((int)$output);
	}

	/**
	 * admin/model/catalog/product/editProduct/after: $args = array(product_id, data)
	 */
	public function afterProductEdit(&$route, &$args, &$output) {
		$this->update(is_array($args) && isset($args[0]) ? (int)$args[0] : 0);
	}

	/**
	 * admin/model/catalog/product/deleteProduct/after: $args = array(product_id); the rows of a missing product are just removed
	 */
	public function afterProductDelete(&$route, &$args, &$output) {
		$this->update(is_array($args) && isset($args[0]) ? (int)$args[0] : 0);
	}

	private function update($product_id) {
		if ($product_id < 1 || !$this->config->get('module_ocs_super_search_built_at')) {
			return;
		}

		try {
			$index = new \Ocs\SuperSearchIndex($this->db, DB_PREFIX, null);
			$index->reindex(array($product_id));
			$index->touch();
		} catch (\Exception $e) {
			$this->log->write('[ocs_super_search] ' . $e->getMessage());
		}
	}
}
