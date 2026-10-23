<?php
// Extension: ocs_preorder. Swaps the Buy button for a pre-order button on unavailable products. Owner: SPEC-025 (REQ-QOR-010, 011).
// Handlers never throw to the customer (REQ-EXT-062).

class ControllerExtensionOcsPreorderEvents extends Controller {
	public function beforeProduct(&$route, &$data) {
		if (!is_array($data) || !$this->config->get('module_ocs_preorder_status') || $this->config->get('config_theme') != 'ocs' || empty($data['product_id'])) {
			return;
		}

		$this->load->model('catalog/product');

		$product = $this->model_catalog_product->getProduct((int)$data['product_id']);

		if (!$product) {
			return;
		}

		$overrides = \Ocs\OrderRequest::parseOverrides($this->config->get('module_ocs_preorder_overrides'));
		$override = isset($overrides[(int)$product['product_id']]) ? $overrides[(int)$product['product_id']] : array();

		$key = 'ocs_preorder';

		if (!\Ocs\OrderRequest::preorderApplies($this->config->get('module_ocs_preorder_global'), $product['quantity'], $override)) {
			// REQ-OPT-001: the product is in stock, but a chosen option value may not be: the button is rendered hidden for that case
			$this->load->model('extension/ocs_request/options');

			if (!\Ocs\Stock::optionRuleOn($this->config->get('module_ocs_preorder_global'), $override) || !$this->model_extension_ocs_request_options->outValues((int)$product['product_id'])) {
				return;
			}

			$key = 'ocs_preorder_opt';
		}

		$strings = $this->load->language('extension/module/ocs_preorder');
		$strings = is_array($strings) ? $strings : array();

		$l = (array)$this->config->get('module_ocs_preorder_l');
		$l = isset($l[$this->config->get('config_language')]) && is_array($l[$this->config->get('config_language')]) ? $l[$this->config->get('config_language')] : array();

		$note = !empty($l['message']) ? \Ocs\OrderRequest::e($l['message']) : (isset($strings['text_message']) ? $strings['text_message'] : '');

		if (!empty($override['date']) && isset($strings['text_expected'])) {
			$note .= ' ' . sprintf($strings['text_expected'], \Ocs\OrderRequest::e(date($this->language->get('date_format_short'), strtotime($override['date']))));
		}

		$data[$key] = array(
			'text' => !empty($l['button']) ? \Ocs\OrderRequest::e($l['button']) : (isset($strings['button_preorder']) ? $strings['button_preorder'] : ''),
			'note' => trim($note),
			'form' => $this->url->link('extension/module/ocs_preorder/form', '', true)
		);
	}
}
