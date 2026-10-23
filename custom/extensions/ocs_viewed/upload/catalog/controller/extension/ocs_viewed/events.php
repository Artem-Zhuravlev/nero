<?php
// Extension: ocs_viewed. Records product page views. Owner: SPEC-030 (REQ-VWD-001).
// Handlers never throw to the customer (REQ-EXT-062).

class ControllerExtensionOcsViewedEvents extends Controller {
	/**
	 * catalog/controller/product/product/before
	 */
	public function beforeProduct(&$route, &$args) {
		if (!$this->config->get('module_ocs_viewed_status') || empty($this->request->get['product_id'])) {
			return;
		}

		try {
			$product_id = (int)$this->request->get['product_id'];

			$this->load->model('catalog/product');

			// only products that exist and are visible are remembered
			if ($this->model_catalog_product->getProduct($product_id)) {
				$this->load->model('extension/ocs_viewed/history');

				$this->model_extension_ocs_viewed_history->record($product_id);
			}
		} catch (\Exception $e) {
			$this->log->write('[ocs_viewed] ' . $e->getMessage());
		}
	}
}
