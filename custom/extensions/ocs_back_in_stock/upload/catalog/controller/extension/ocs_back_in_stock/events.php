<?php
// Extension: ocs_back_in_stock. Offers the subscription on out-of-stock products. Owner: SPEC-025 (REQ-QOR-020).
// Handlers never throw to the customer (REQ-EXT-062).

class ControllerExtensionOcsBackInStockEvents extends Controller {
	public function beforeProduct(&$route, &$data) {
		if (!is_array($data) || !$this->config->get('module_ocs_back_in_stock_status') || $this->config->get('config_theme') != 'ocs' || empty($data['product_id'])) {
			return;
		}

		$this->load->model('catalog/product');

		$product = $this->model_catalog_product->getProduct((int)$data['product_id']);

		if (!$product || (int)$product['quantity'] > 0) {
			return;
		}

		$strings = $this->load->language('extension/module/ocs_back_in_stock');

		$data['ocs_subscribe'] = array(
			'text' => is_array($strings) && isset($strings['button_subscribe']) ? $strings['button_subscribe'] : '',
			'form' => $this->url->link('extension/module/ocs_back_in_stock/form', '', true)
		);
	}
}
