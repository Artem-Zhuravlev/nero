<?php
// Extension: ocs_novaposhta. Events: load the checkout component on checkout pages; write the chosen point into the order. Owner: SPEC-085.
// Handlers never throw to the customer (REQ-EXT-062).

class ControllerExtensionOcsNovaposhtaEvents extends Controller {
	/**
	 * catalog/view/common/header/before: the script and style are added only on checkout pages (REQ-NP-042).
	 */
	public function beforeHeader(&$route, &$data) {
		if (!is_array($data) || !$this->config->get('shipping_ocs_novaposhta_status')) {
			return;
		}

		$page = isset($this->request->get['route']) ? (string)$this->request->get['route'] : '';

		if (!preg_match('#^(checkout/(checkout|simplecheckout\w*)|simplecheckout\w*)$#', $page)) {
			return;
		}

		$version = '1.0.0';

		$data['scripts'][] = 'catalog/view/javascript/ocs/novaposhta.js?v=' . $version;
		$data['styles'][] = array('href' => 'catalog/view/theme/default/stylesheet/ocs_novaposhta.css?v=' . $version, 'rel' => 'stylesheet', 'media' => 'screen');
	}

	/**
	 * catalog/model/checkout/order/addOrder/before: $args[0] is the order data.
	 */
	public function beforeAddOrder(&$route, &$args) {
		if (isset($args[0]) && is_array($args[0])) {
			$this->apply($args[0]);
		}
	}

	/**
	 * catalog/model/checkout/order/editOrder/before: $args = [order_id, data].
	 */
	public function beforeEditOrder(&$route, &$args) {
		if (isset($args[1]) && is_array($args[1])) {
			$this->apply($args[1]);
		}
	}

	/**
	 * catalog/model/checkout/order/addOrder/after: $output is the new order id.
	 */
	public function afterAddOrder(&$route, &$args, &$output) {
		$this->remember((int)$output);
	}

	public function afterEditOrder(&$route, &$args) {
		$this->remember(isset($args[0]) ? (int)$args[0] : 0);
	}

	private function isOurs($code) {
		return strpos((string)$code, 'ocs_novaposhta') === 0;
	}

	/** Puts the point's city and name into the order's shipping fields and method title (REQ-NP-031). */
	private function apply(array &$data) {
		try {
			if (!$this->config->get('shipping_ocs_novaposhta_status') || !isset($data['shipping_code']) || !$this->isOurs($data['shipping_code'])) {
				return;
			}

			$this->load->model('extension/shipping/ocs_novaposhta');

			$point = $this->model_extension_shipping_ocs_novaposhta->selectedPoint();
			$lang = $this->model_extension_shipping_ocs_novaposhta->lang();

			if (!$point) {
				if ($this->config->get('shipping_ocs_novaposhta_require_point')) {
					$data['comment'] = trim((isset($data['comment']) ? $data['comment'] : '') . "\n[Nova Poshta: point not confirmed]");
				}

				return;
			}

			$data['shipping_city'] = $point['city_' . $lang];
			$data['shipping_address_1'] = $point['name_' . $lang];
			$data['shipping_method'] = $this->model_extension_shipping_ocs_novaposhta->methodTitle() . ': ' . $point['name_' . $lang];
		} catch (\Exception $e) {
			$this->log->write('ocs_novaposhta: ' . $e->getMessage());
		}
	}

	private function remember($order_id) {
		try {
			if ($order_id <= 0 || !$this->config->get('shipping_ocs_novaposhta_status')) {
				return;
			}

			$method = isset($this->session->data['shipping_method']['code']) ? $this->session->data['shipping_method']['code'] : '';

			if (!$this->isOurs($method)) {
				return;
			}

			$this->load->model('extension/shipping/ocs_novaposhta');

			$point = $this->model_extension_shipping_ocs_novaposhta->selectedPoint();

			if ($point) {
				$this->db->query("REPLACE INTO `" . DB_PREFIX . "ocs_np_order` SET order_id = '" . (int)$order_id . "', city_ref = '" . $this->db->escape($point['city_ref']) . "', warehouse_ref = '" . $this->db->escape($point['warehouse_ref']) . "', type = '" . $this->db->escape($point['type']) . "', date_added = NOW()");
			}
		} catch (\Exception $e) {
			$this->log->write('ocs_novaposhta: ' . $e->getMessage());
		}
	}
}
