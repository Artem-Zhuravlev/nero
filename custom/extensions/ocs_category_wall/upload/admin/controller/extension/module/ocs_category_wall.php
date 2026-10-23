<?php
// Extension: ocs_category_wall. Admin settings. Owner: SPEC-027 (REQ-MNU-010, 011).

class ControllerExtensionModuleOcsCategoryWall extends Controller {
	private $error = array();

	private $defaults = array(
		'status' => 0, 'cols_desktop' => 4, 'cols_tablet' => 3, 'cols_mobile' => 2, 'sort' => 'sort', 'source' => 'top',
		'selected' => '', 'show_children' => 1, 'children_limit' => 5, 'image_width' => 200, 'image_height' => 200
	);

	public function index() {
		$this->load->language('extension/module/ocs_category_wall');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		$token = 'user_token=' . $this->session->data['user_token'];

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_ocs_category_wall', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', $token . '&type=module', true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', $token, true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', $token . '&type=module', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/ocs_category_wall', $token, true)
		);

		$data['action'] = $this->url->link('extension/module/ocs_category_wall', $token, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=module', true);

		foreach ($this->defaults as $field => $default) {
			$key = 'module_ocs_category_wall_' . $field;

			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} elseif ($this->config->has($key)) {
				$data[$key] = $this->config->get($key);
			} else {
				$data[$key] = $default;
			}
		}

		$data['page_url'] = HTTPS_CATALOG . 'index.php?route=extension/module/ocs_category_wall/page';
		$data['version'] = '1.0.0';

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_category_wall', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/ocs_category_wall')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$p = $this->request->post;

		foreach (array('cols_desktop' => 6, 'cols_tablet' => 4, 'cols_mobile' => 3) as $field => $max) {
			$v = isset($p['module_ocs_category_wall_' . $field]) ? (int)$p['module_ocs_category_wall_' . $field] : 0;

			if ($v < 1 || $v > $max) {
				$this->error['warning'] = $this->language->get('error_values');
			}
		}

		foreach (array('image_width', 'image_height') as $field) {
			if (empty($p['module_ocs_category_wall_' . $field]) || (int)$p['module_ocs_category_wall_' . $field] < 1) {
				$this->error['warning'] = $this->language->get('error_values');
			}
		}

		if (isset($p['module_ocs_category_wall_selected']) && $p['module_ocs_category_wall_selected'] !== '' && !preg_match('/^\d+(\s*,\s*\d+)*$/', trim($p['module_ocs_category_wall_selected']))) {
			$this->error['warning'] = $this->language->get('error_values');
		}

		return !$this->error;
	}
}
