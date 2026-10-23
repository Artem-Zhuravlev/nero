<?php
// Extension: ocs_category_menu. Compact category tree for column positions. Owner: SPEC-027 (REQ-MNU-020, 021).

class ControllerExtensionModuleOcsCategoryMenu extends Controller {
	public function index() {
		if (!$this->config->get('module_ocs_category_menu_status')) {
			return '';
		}

		$this->load->language('extension/module/ocs_category_menu');
		$this->load->model('extension/ocs_menu/tree');

		$levels = max(1, min(3, (int)$this->config->get('module_ocs_category_menu_levels') ?: 3));

		$tree = \Ocs\CategoryTree::sort($this->model_extension_ocs_menu_tree->getTree($levels), 'sort');

		$active = isset($this->request->get['path']) ? \Ocs\CategoryTree::pathIds($this->request->get['path']) : array();

		$data['categories'] = \Ocs\CategoryTree::markActive($tree, $active);
		$data['heading_title'] = $this->language->get('heading_title');

		return $data['categories'] ? $this->load->view('extension/module/ocs_category_menu', $data) : '';
	}
}
