<?php
// Extension: ocs_cookie. View event feeding the cookie popup into the footer template of the ocs theme. Owner: SPEC-083.
// The handler never throws to the customer (REQ-EXT-062): on any problem the template gets no `ocs_cookie`.

class ControllerExtensionOcsCookieEvents extends Controller {
	/**
	 * catalog/view/common/footer/before
	 */
	public function beforeFooter(&$route, &$data) {
		if (!is_array($data) || !$this->config->get('module_ocs_cookie_status') || $this->config->get('config_theme') != 'ocs') {
			return;
		}

		$strings = $this->load->language('extension/ocs_cookie/cookie');
		$strings = is_array($strings) ? $strings : array();

		$cookie = \Ocs\Cookie::build(array(
			'l'        => (array)$this->config->get('module_ocs_cookie_l'),
			'position' => $this->config->get('module_ocs_cookie_position'),
			'version'  => $this->config->get('module_ocs_cookie_version'),
			'days'     => $this->config->get('module_ocs_cookie_days')
		), $this->config->get('config_language'));

		if ($cookie) {
			$cookie['label'] = isset($strings['ocs_cookie_label']) ? $strings['ocs_cookie_label'] : '';
			$cookie['reopen'] = isset($strings['ocs_cookie_reopen']) ? $strings['ocs_cookie_reopen'] : '';
		}

		$data['ocs_cookie'] = $cookie;
	}
}
