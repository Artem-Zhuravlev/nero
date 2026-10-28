<?php
// Extension: ocs_cookie. Admin settings: texts, terms link and behaviour of the cookie popup. Owner: SPEC-083.

class ControllerExtensionModuleOcsCookie extends Controller {
	private $error = array();

	private $globals = array('status' => 0, 'position' => 'card', 'version' => 1, 'days' => 365);

	private $per_language = array('title', 'text', 'link_label', 'link_url', 'accept', 'necessary');

	public function index() {
		$this->load->language('extension/module/ocs_cookie');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('localisation/language');

		$token = 'user_token=' . $this->session->data['user_token'];

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_ocs_cookie', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', $token . '&type=module', true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['error_number'] = isset($this->error['number']) ? $this->error['number'] : '';
		$data['error_text'] = isset($this->error['text']) ? $this->error['text'] : '';

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
			'href' => $this->url->link('extension/module/ocs_cookie', $token, true)
		);

		$data['action'] = $this->url->link('extension/module/ocs_cookie', $token, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=module', true);

		foreach ($this->globals as $field => $default) {
			$key = 'module_ocs_cookie_' . $field;

			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} elseif ($this->config->has($key)) {
				$data[$key] = $this->config->get($key);
			} else {
				$data[$key] = $default;
			}
		}

		if (isset($this->request->post['module_ocs_cookie_l'])) {
			$l = $this->request->post['module_ocs_cookie_l'];
		} else {
			$l = (array)$this->config->get('module_ocs_cookie_l');
		}

		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['l'] = array();

		foreach ($data['languages'] as $language) {
			foreach ($this->per_language as $field) {
				$data['l'][$language['code']][$field] = isset($l[$language['code']][$field]) ? $l[$language['code']][$field] : '';
			}
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_cookie', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/ocs_cookie')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		foreach (array('version', 'days') as $field) {
			$value = isset($this->request->post['module_ocs_cookie_' . $field]) ? $this->request->post['module_ocs_cookie_' . $field] : '';

			if (!preg_match('/^\d{1,4}$/', (string)$value) || (int)$value < 1) {
				$this->error['number'] = $this->language->get('error_number');
			}
		}

		if (isset($this->request->post['module_ocs_cookie_l']) && is_array($this->request->post['module_ocs_cookie_l'])) {
			foreach ($this->request->post['module_ocs_cookie_l'] as $fields) {
				$text = isset($fields['text']) ? trim($fields['text']) : '';
				$necessary = isset($fields['necessary']) ? trim($fields['necessary']) : '';

				// a filled language needs the text and the second button label; an empty text simply hides the popup for that language
				if ($text !== '' && $necessary === '') {
					$this->error['text'] = $this->language->get('error_text');
				}
			}
		}

		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('error_form');
		}

		return !$this->error;
	}
}
