<?php
// Extension: ocs_ajax_search. Admin settings and popular queries. Owner: SPEC-028 (REQ-SRC-003, 010).

class ControllerExtensionModuleOcsAjaxSearch extends Controller {
	private $error = array();

	private $defaults = array('status' => 0, 'limit' => 6, 'min_length' => 2, 'categories' => 1, 'brands' => 1);

	public function index() {
		$this->load->language('extension/module/ocs_ajax_search');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('localisation/language');

		$token = 'user_token=' . $this->session->data['user_token'];

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_ocs_ajax_search', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', $token . '&type=module', true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $token . '&type=module', true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/ocs_ajax_search', $token, true));

		$data['action'] = $this->url->link('extension/module/ocs_ajax_search', $token, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=module', true);

		foreach ($this->defaults as $field => $default) {
			$key = 'module_ocs_ajax_search_' . $field;

			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} elseif ($this->config->has($key)) {
				$data[$key] = $this->config->get($key);
			} else {
				$data[$key] = $default;
			}
		}

		$l = isset($this->request->post['module_ocs_ajax_search_l']) ? $this->request->post['module_ocs_ajax_search_l'] : (array)$this->config->get('module_ocs_ajax_search_l');

		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['l'] = array();

		foreach ($data['languages'] as $language) {
			$data['l'][$language['code']] = array('popular' => isset($l[$language['code']]['popular']) ? $l[$language['code']]['popular'] : '');
		}

		$data['version'] = '1.0.0';

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_ajax_search', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/ocs_ajax_search')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$limit = isset($this->request->post['module_ocs_ajax_search_limit']) ? (int)$this->request->post['module_ocs_ajax_search_limit'] : 0;
		$min = isset($this->request->post['module_ocs_ajax_search_min_length']) ? (int)$this->request->post['module_ocs_ajax_search_min_length'] : 0;

		if ($limit < 1 || $limit > 12 || $min < 1 || $min > 5) {
			$this->error['warning'] = $this->language->get('error_values');
		}

		return !$this->error;
	}
}
