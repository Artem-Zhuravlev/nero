<?php
// Extension: ocs_mega_menu. View event that replaces the core category menu of the ocs theme. Owner: SPEC-027.
// Handlers never throw to the customer (REQ-EXT-062).

class ControllerExtensionOcsMegaMenuEvents extends Controller {
	/**
	 * catalog/view/common/menu/before: hands the rendered mega menu to the theme's common/menu.twig.
	 */
	public function beforeMenu(&$route, &$data) {
		if (!is_array($data) || !$this->config->get('module_ocs_mega_menu_status') || $this->config->get('config_theme') != 'ocs') {
			return;
		}

		$html = $this->load->controller('extension/module/ocs_mega_menu');

		if (is_string($html) && $html !== '') {
			$data['ocs_mega'] = $html;
		}
	}
}
