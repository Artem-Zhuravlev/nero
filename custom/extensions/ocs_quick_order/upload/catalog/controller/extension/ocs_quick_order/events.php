<?php
// Extension: ocs_quick_order. View events that expose the quick-order button to the theme templates. Owner: SPEC-025 (REQ-QOR-001).
// Handlers never throw to the customer (REQ-EXT-062).

class ControllerExtensionOcsQuickOrderEvents extends Controller {
	public function beforeProduct(&$route, &$data) {
		$this->expose($data);
	}

	private function expose(&$data) {
		if (!is_array($data) || !$this->config->get('module_ocs_quick_order_status') || $this->config->get('config_theme') != 'ocs') {
			return;
		}

		$strings = $this->load->language('extension/module/ocs_quick_order');

		$data['ocs_quick'] = array(
			'text' => is_array($strings) && isset($strings['button_quick']) ? $strings['button_quick'] : '',
			'form' => $this->url->link('extension/module/ocs_quick_order/form', '', true)
		);
	}
}
