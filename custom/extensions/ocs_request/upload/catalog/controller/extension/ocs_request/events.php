<?php
// Extension: ocs_request. Keeps out-of-stock option values selectable on the product page when pre-order or "notify me" can serve them.
// Owner: SPEC-037 (REQ-OPT-001). Registered by ocs_preorder and ocs_back_in_stock; the handler never throws to the customer (REQ-EXT-062).

class ControllerExtensionOcsRequestEvents extends Controller {
	public function beforeProduct(&$route, &$data) {
		if (!is_array($data) || empty($data['product_id']) || !empty($data['ocs_options_out']) || $this->config->get('config_theme') != 'ocs') {
			return;
		}

		if (!$this->config->get('module_ocs_preorder_status') && !$this->config->get('module_ocs_back_in_stock_status')) {
			return;
		}

		try {
			$this->load->model('catalog/product');
			$this->load->model('extension/ocs_request/options');

			$product = $this->model_catalog_product->getProduct((int)$data['product_id']);

			if (!$product || !$this->model_extension_ocs_request_options->outValues((int)$data['product_id'])) {
				return;
			}

			$this->load->language('extension/ocs_request/common');

			$data['options'] = $this->model_extension_ocs_request_options->build($product, true);
			$data['ocs_options_out'] = true;
			$data['ocs_text_out'] = $this->language->get('text_out_of_stock');
		} catch (\Exception $e) {
			$this->log->write('[ocs_request] ' . $e->getMessage());
		}
	}
}
