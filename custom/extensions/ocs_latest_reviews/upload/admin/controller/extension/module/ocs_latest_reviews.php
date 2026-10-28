<?php
// Extension: ocs_latest_reviews. Admin: one form per instance (core module table). Owner: SPEC-081 (REQ-LRV-001, REQ-LRV-002).

class ControllerExtensionModuleOcsLatestReviews extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/ocs_latest_reviews');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/module');
		$this->load->model('localisation/language');

		$token = 'user_token=' . $this->session->data['user_token'];
		$module = isset($this->request->get['module_id']) ? '&module_id=' . (int)$this->request->get['module_id'] : '';

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			if (!isset($this->request->get['module_id'])) {
				$this->model_setting_module->addModule('ocs_latest_reviews', $this->request->post);
			} else {
				$this->model_setting_module->editModule($this->request->get['module_id'], $this->request->post);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', $token . '&type=module', true));
		}

		foreach (array('warning', 'name', 'limit', 'length', 'min_rating') as $key) {
			$data['error_' . $key] = isset($this->error[$key]) ? $this->error[$key] : '';
		}

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $token . '&type=module', true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/ocs_latest_reviews', $token . $module, true));

		$data['action'] = $this->url->link('extension/module/ocs_latest_reviews', $token . $module, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=module', true);

		$info = array();

		if (isset($this->request->get['module_id']) && $this->request->server['REQUEST_METHOD'] != 'POST') {
			$info = $this->model_setting_module->getModule($this->request->get['module_id']);
		}

		$fields = array('name' => '', 'status' => 1, 'limit' => 6, 'length' => 200, 'min_rating' => 1, 'by_category' => 0);

		foreach ($fields as $field => $default) {
			if (isset($this->request->post[$field])) {
				$data[$field] = $this->request->post[$field];
			} elseif (isset($info[$field])) {
				$data[$field] = $info[$field];
			} else {
				$data[$field] = $default;
			}
		}

		$data['title'] = isset($this->request->post['title']) ? $this->request->post['title'] : (isset($info['title']) ? $info['title'] : array());
		$data['languages'] = $this->model_localisation_language->getLanguages();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_latest_reviews', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/ocs_latest_reviews')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$post = $this->request->post;

		if (!isset($post['name']) || utf8_strlen($post['name']) < 3 || utf8_strlen($post['name']) > 64) {
			$this->error['name'] = $this->language->get('error_name');
		}

		$ranges = array('limit' => array(1, 20), 'length' => array(40, 1000), 'min_rating' => array(1, 5));

		foreach ($ranges as $key => $range) {
			if (!isset($post[$key]) || !ctype_digit((string)$post[$key]) || (int)$post[$key] < $range[0] || (int)$post[$key] > $range[1]) {
				$this->error[$key] = $this->language->get('error_' . $key);
			}
		}

		return !$this->error;
	}
}
