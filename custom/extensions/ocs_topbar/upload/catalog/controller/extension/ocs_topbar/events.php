<?php
// Extension: ocs_topbar. View event feeding the top bar into the header template of the ocs theme. Owner: SPEC-082.
// The handler never throws to the customer (REQ-EXT-062): on any problem the template gets no `ocs_topbar`.

class ControllerExtensionOcsTopbarEvents extends Controller {
	/**
	 * catalog/view/common/header/before
	 */
	public function beforeHeader(&$route, &$data) {
		if (!is_array($data) || !$this->config->get('module_ocs_topbar_status') || $this->config->get('config_theme') != 'ocs') {
			return;
		}

		$strings = $this->load->language('extension/ocs_topbar/topbar');
		$strings = is_array($strings) ? $strings : array();

		$code = $this->config->get('config_language'); // language directory code (uk-ua)

		$topbar = \Ocs\Topbar::build(array(
			'l'         => (array)$this->config->get('module_ocs_topbar_l'),
			'from'      => $this->config->get('module_ocs_topbar_from'),
			'to'        => $this->config->get('module_ocs_topbar_to'),
			'bg'        => $this->config->get('module_ocs_topbar_bg'),
			'bg_custom' => $this->config->get('module_ocs_topbar_bg_custom'),
			'closable'  => $this->config->get('module_ocs_topbar_closable'),
			'btn_style' => $this->config->get('module_ocs_topbar_btn_style'),
			'blank'     => $this->config->get('module_ocs_topbar_blank')
		), $code, time());

		if ($topbar) {
			$topbar['label'] = isset($strings['ocs_topbar_label']) ? $strings['ocs_topbar_label'] : '';
			$topbar['close'] = isset($strings['ocs_topbar_close']) ? $strings['ocs_topbar_close'] : '';
		}

		$data['ocs_topbar'] = $topbar;
	}
}
