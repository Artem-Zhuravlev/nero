<?php
// Extension: ocs_brand_strip. Admin settings. Owner: SPEC-033 (REQ-HOM-010).

class ControllerExtensionModuleOcsBrandStrip extends Controller {
	private $error = array();

	private $defaults = array('status' => 0, 'source' => 'all', 'selected' => '', 'sort' => 'name', 'limit' => 20, 'width' => 140, 'height' => 80);

	public function index() {
		$this->load->language('extension/module/ocs_brand_strip');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		$token = 'user_token=' . $this->session->data['user_token'];

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_ocs_brand_strip', $this->request->post);

			$this->cache->delete('ocs_brands');

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', $token . '&type=module', true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $token . '&type=module', true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/ocs_brand_strip', $token, true));

		$data['action'] = $this->url->link('extension/module/ocs_brand_strip', $token, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=module', true);

		foreach ($this->defaults as $field => $default) {
			$key = 'module_ocs_brand_strip_' . $field;

			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} elseif ($this->config->has($key)) {
				$data[$key] = $this->config->get($key);
			} else {
				$data[$key] = $default;
			}
		}

		$data['version'] = '1.0.0';

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_brand_strip', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/ocs_brand_strip')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$p = $this->request->post;

		foreach (array('limit' => array(1, 60), 'width' => array(40, 400), 'height' => array(30, 300)) as $field => $range) {
			$v = isset($p['module_ocs_brand_strip_' . $field]) ? $p['module_ocs_brand_strip_' . $field] : '';

			if (!ctype_digit((string)$v) || (int)$v < $range[0] || (int)$v > $range[1]) {
				$this->error['warning'] = $this->language->get('error_values');
			}
		}

		if (!isset($p['module_ocs_brand_strip_source']) || !in_array($p['module_ocs_brand_strip_source'], array('all', 'selected'), true) || !isset($p['module_ocs_brand_strip_sort']) || !in_array($p['module_ocs_brand_strip_sort'], array('name', 'sort'), true)) {
			$this->error['warning'] = $this->language->get('error_values');
		}

		if (!empty($p['module_ocs_brand_strip_selected']) && !preg_match('/^\d+(\s*,\s*\d+)*$/', trim($p['module_ocs_brand_strip_selected']))) {
			$this->error['warning'] = $this->language->get('error_selected');
		}

		return !$this->error;
	}
}
