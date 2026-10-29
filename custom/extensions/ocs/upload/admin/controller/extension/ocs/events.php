<?php
// Extension: ocs (theme). Admin events: the "Nero settings" entry of the main menu. Owner: SPEC-123 (REQ-NST-005).
// Handlers never throw to the admin user.

class ControllerExtensionOcsEvents extends Controller {
	/**
	 * admin/view/common/column_left/before: top-level entry right after the dashboard, only with access to the theme form.
	 */
	public function beforeMenu(&$route, &$data) {
		if (!is_array($data) || !isset($data['menus']) || !is_array($data['menus']) || !$this->user->hasPermission('access', 'extension/theme/ocs')) {
			return;
		}

		try {
			$this->load->language('extension/ocs/nero', 'ocs_nero');

			$item = array(
				'id'       => 'menu-nero',
				'icon'     => 'fa-sliders',
				'name'     => $this->language->get('ocs_nero')->get('text_menu'),
				'href'     => $this->url->link('extension/theme/ocs', 'user_token=' . $this->session->data['user_token'], true),
				'children' => array()
			);

			$position = 0;

			foreach ($data['menus'] as $i => $menu) {
				if (isset($menu['id']) && $menu['id'] == 'menu-dashboard') {
					$position = $i + 1;
					break;
				}
			}

			array_splice($data['menus'], $position, 0, array($item));
		} catch (\Exception $e) {
			$this->log->write('[ocs] ' . $e->getMessage());
		}
	}
}
