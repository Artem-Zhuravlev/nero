<?php
// Extension: ocs_back_in_stock. Offers the subscription on out-of-stock products. Owner: SPEC-025 (REQ-QOR-020).
// Handlers never throw to the customer (REQ-EXT-062).

class ControllerExtensionOcsBackInStockEvents extends Controller {
	/**
	 * catalog/view/common/header/before: form URL and label for the bell button of out-of-stock cards (SPEC-077 REQ-OOS-001..003).
	 */
	public function beforeHeader(&$route, &$data) {
		if (!is_array($data) || !$this->config->get('module_ocs_back_in_stock_status') || $this->config->get('config_theme') != 'ocs') {
			return;
		}

		$strings = $this->load->language('extension/module/ocs_back_in_stock');

		$data['ocs_notify'] = array(
			'text' => is_array($strings) && isset($strings['button_subscribe']) ? $strings['button_subscribe'] : '',
			'form' => $this->url->link('extension/module/ocs_back_in_stock/form', '', true)
		);
	}

	public function beforeProduct(&$route, &$data) {
		if (!is_array($data) || !$this->config->get('module_ocs_back_in_stock_status') || $this->config->get('config_theme') != 'ocs' || empty($data['product_id'])) {
			return;
		}

		$this->load->model('catalog/product');

		$product = $this->model_catalog_product->getProduct((int)$data['product_id']);

		if (!$product) {
			return;
		}

		$key = 'ocs_subscribe';

		if ((int)$product['quantity'] > 0) {
			// REQ-OPT-001: in stock, but a chosen option value may not be: the button is rendered hidden for that case
			$this->load->model('extension/ocs_request/options');

			if (!$this->model_extension_ocs_request_options->outValues((int)$product['product_id'])) {
				return;
			}

			$key = 'ocs_subscribe_opt';
		}

		$strings = $this->load->language('extension/module/ocs_back_in_stock');

		$data[$key] = array(
			'text' => is_array($strings) && isset($strings['button_subscribe']) ? $strings['button_subscribe'] : '',
			'form' => $this->url->link('extension/module/ocs_back_in_stock/form', '', true)
		);
	}
}
