<?php
// Extension: ocs_ajax_cart. View events: panel URL for the header and availability labels for cards and the product page.
// Owner: SPEC-026 (REQ-CRT-001, REQ-CRT-010). Handlers never throw to the customer (REQ-EXT-062).

class ControllerExtensionOcsAjaxCartEvents extends Controller {
	/**
	 * catalog/view/common/header/before: switches the panel on for theme.js.
	 */
	public function beforeHeader(&$route, &$data) {
		if (!is_array($data) || !$this->enabled()) {
			return;
		}

		$data['ocs_cart'] = array('url' => $this->url->link('extension/module/ocs_ajax_cart/panel', '', true));
	}

	public function beforeCategory(&$route, &$data) {
		$this->labels($data);
	}

	public function beforeSearch(&$route, &$data) {
		$this->labels($data);
	}

	public function beforeManufacturer(&$route, &$data) {
		$this->labels($data);
	}

	public function beforeSpecial(&$route, &$data) {
		$this->labels($data);
	}

	/**
	 * catalog/view/product/product/before
	 */
	public function beforeProduct(&$route, &$data) {
		if (!is_array($data) || !$this->enabled() || empty($data['product_id'])) {
			return;
		}

		$this->load->model('catalog/product');

		$product = $this->model_catalog_product->getProduct((int)$data['product_id']);

		if ($product) {
			$data['ocs_stock'] = $this->label((int)$product['quantity']);
		}
	}

	private function labels(&$data) {
		if (!is_array($data) || !$this->enabled() || empty($data['products']) || !is_array($data['products'])) {
			return;
		}

		foreach ($data['products'] as $i => $product) {
			// quantity comes from the ocs_listing events (same trigger, lower sort order)
			if (isset($product['quantity'])) {
				$data['products'][$i]['ocs_stock'] = $this->label((int)$product['quantity']);
			}
		}
	}

	/**
	 * @return array state (in|low|out), label
	 */
	private function label($quantity) {
		$strings = $this->load->language('extension/module/ocs_ajax_cart');
		$strings = is_array($strings) ? $strings : array();

		$low = (int)$this->config->get('module_ocs_ajax_cart_low_stock');

		if ($quantity <= 0) {
			return array('state' => 'out', 'label' => isset($strings['text_out_of_stock']) ? $strings['text_out_of_stock'] : '');
		}

		if ($low > 0 && $quantity <= $low) {
			return array('state' => 'low', 'label' => sprintf(isset($strings['text_few_left']) ? $strings['text_few_left'] : '%d', $quantity));
		}

		return array('state' => 'in', 'label' => isset($strings['text_in_stock']) ? $strings['text_in_stock'] : '');
	}

	private function enabled() {
		return (bool)$this->config->get('module_ocs_ajax_cart_status') && $this->config->get('config_theme') == 'ocs';
	}
}
