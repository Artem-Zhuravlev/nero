<?php
// Extension: ocs_shell. View events feeding the header and footer templates of the ocs theme. Owner: SPEC-031.
// Handlers never throw to the customer (REQ-EXT-062): on any problem the templates simply get no `ocs_shell`.

class ControllerExtensionOcsShellEvents extends Controller {
	/**
	 * catalog/view/common/header/before
	 */
	public function beforeHeader(&$route, &$data) {
		if (!is_array($data) || !$this->enabled()) {
			return;
		}

		$data['ocs_shell'] = $this->build(false);
	}

	/**
	 * catalog/view/common/footer/before
	 */
	public function beforeFooter(&$route, &$data) {
		if (!is_array($data) || !$this->enabled()) {
			return;
		}

		$data['ocs_shell'] = $this->build(true);
	}

	private function enabled() {
		return (bool)$this->config->get('module_ocs_shell_status') && $this->config->get('config_theme') == 'ocs';
	}

	private function build($footer) {
		$strings = $this->load->language('extension/ocs_shell/shell');
		$strings = is_array($strings) ? $strings : array();

		$code = $this->config->get('config_language'); // language directory code (uk-ua); language->get('code') is the HTML code (uk)
		$settings = array(
			'l'                 => (array)$this->config->get('module_ocs_shell_l'),
			'announcement_from' => $this->config->get('module_ocs_shell_announcement_from'),
			'announcement_to'   => $this->config->get('module_ocs_shell_announcement_to'),
			'telegram'          => $this->config->get('module_ocs_shell_telegram'),
			'viber'             => $this->config->get('module_ocs_shell_viber'),
			'messenger'         => $this->config->get('module_ocs_shell_messenger')
		);

		$l = isset($settings['l'][$code]) && is_array($settings['l'][$code]) ? $settings['l'][$code] : array();

		$route = isset($this->request->get['route']) ? (string)$this->request->get['route'] : 'common/home';

		$shell = array(
			'announcement' => \Ocs\Shell::announcement($settings, $code, time()),
			'phones'       => \Ocs\Shell::parsePhones($this->config->get('module_ocs_shell_phones')),
			'hours'        => isset($l['hours']) ? \Ocs\Shell::e($l['hours']) : '',
			'messengers'   => \Ocs\Shell::messengers($settings),
			'links'        => \Ocs\Shell::parseLinks(isset($l['links']) ? $l['links'] : ''),
			'mobile_bar'   => (bool)$this->config->get('module_ocs_shell_mobile_bar') && strpos($route, 'checkout/') !== 0,
			't'            => $strings
		);

		$compare = isset($this->session->data['compare']) ? count($this->session->data['compare']) : 0;
		$shell['compare'] = $this->url->link('product/compare');
		$shell['compare_text'] = \Ocs\Shell::e(sprintf(isset($strings['ocs_compare']) ? $strings['ocs_compare'] : '%s', $compare));

		if ($footer) {
			$shell['groups'] = \Ocs\Shell::parseGroups(isset($l['footer_groups']) ? $l['footer_groups'] : '');
			$shell['address'] = isset($l['address']) ? \Ocs\Shell::e($l['address']) : '';
			$shell['social'] = \Ocs\Shell::parseLinks($this->config->get('module_ocs_shell_social'));
			$shell['apps'] = \Ocs\Shell::parseLinks($this->config->get('module_ocs_shell_apps'));
			$shell['home'] = $this->url->link('common/home');
			$shell['cart'] = $this->url->link('checkout/cart');
		}

		return $shell;
	}
}
