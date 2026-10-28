<?php
// Extension: ocs_shell. View events feeding the header and footer templates of the ocs theme. Owner: SPEC-031.
// Handlers never throw to the customer (REQ-EXT-062): on any problem the templates simply get no `ocs_shell`.

class ControllerExtensionOcsShellEvents extends Controller {
	/**
	 * catalog/view/common/header/before
	 */
	public function beforeHeader(&$route, &$data) {
		// the name of a logged-in customer is printed next to the account icon; independent of the shell module status
		if (is_array($data) && $this->config->get('config_theme') == 'ocs') {
			$data['first_name'] = $this->customer->isLogged() ? trim((string)$this->customer->getFirstName()) : '';
		}

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

	/**
	 * catalog/view/any/before (SPEC-054): the core "home" breadcrumb is an icon; the theme prints a word instead.
	 */
	public function beforeView(&$route, &$data) {
		// SPEC-067: the designed contacts page reads the same contact data as the footer
		if ($route === 'information/contact' && is_array($data) && $this->enabled()) {
			$data['ocs_shell'] = $this->build(true);
		}

		if (!is_array($data) || empty($data['breadcrumbs']) || !is_array($data['breadcrumbs']) || !$this->enabled()) {
			return;
		}

		$strings = $this->load->language('extension/ocs_shell/shell');
		$home = is_array($strings) && isset($strings['ocs_breadcrumb_home']) ? $strings['ocs_breadcrumb_home'] : '';

		if ($home === '') {
			return;
		}

		foreach ($data['breadcrumbs'] as &$crumb) {
			if (isset($crumb['text']) && strpos($crumb['text'], 'fa-home') !== false) {
				$crumb['text'] = $home;
			}
		}
		unset($crumb);
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

		$this->load->model('setting/setting');

		// config_language is the visitor's language at run time; the store default is read from the settings table (fallback for untranslated contacts)
		$default = (string)$this->model_setting_setting->getSettingValue('config_language', (int)$this->config->get('config_store_id'));

		// contacts of the theme settings (SPEC-047); the old module fields stay as a fallback until the theme ones are filled in
		$contacts = \Ocs\Contacts::view($this->config->get('theme_ocs_contact'), $this->config->get('theme_ocs_contact_links'), $code, $default);

		if ($contacts['phones'] || $contacts['address'] || $contacts['hours'] || $contacts['links']) {
			$shell['phones'] = $contacts['phones'];
			$shell['hours'] = $contacts['hours'];
			$shell['messengers'] = array_map(function ($m) {
				return array('key' => $m['key'], 'name' => $m['title'], 'title' => $m['title'], 'icon' => $m['icon'], 'href' => $m['href']);
			}, $contacts['messengers']);
			$shell['contact_address'] = $contacts['address'];
			$shell['contact_social'] = $contacts['social'];
		} else {
			$shell['contact_address'] = isset($l['address']) ? \Ocs\Shell::e($l['address']) : '';
			$shell['contact_social'] = array();

			foreach (\Ocs\Shell::parseLinks($this->config->get('module_ocs_shell_social')) as $link) {
				if ($link['href'] !== '') {
					$shell['contact_social'][] = array('icon' => 'i-globe', 'key' => 'site', 'kind' => 'social', 'title' => $link['text'], 'href' => $link['href']);
				}
			}
		}

		if ($footer) {
			$shell['groups'] = \Ocs\Shell::parseGroups(isset($l['footer_groups']) ? $l['footer_groups'] : '');
			$shell['address'] = $shell['contact_address'];
			$shell['social'] = $shell['contact_social'];
			$shell['home'] = $this->url->link('common/home');
			$shell['cart'] = $this->url->link('checkout/cart');
		}

		return $shell;
	}
}
