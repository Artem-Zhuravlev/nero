<?php
// Extension: ocs_shell. Admin settings: announcement, contacts, messengers, links, footer, mobile bar. Owner: SPEC-031.

class ControllerExtensionModuleOcsShell extends Controller {
	private $error = array();

	private $globals = array('status' => 0, 'mobile_bar' => 1, 'announcement_from' => '', 'announcement_to' => '', 'phones' => '', 'telegram' => '', 'viber' => '', 'messenger' => '', 'social' => '');

	private $per_language = array('announcement_text', 'announcement_url', 'hours', 'links', 'address', 'footer_groups');

	public function index() {
		$this->load->language('extension/module/ocs_shell');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('localisation/language');

		$token = 'user_token=' . $this->session->data['user_token'];

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_ocs_shell', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', $token . '&type=module', true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['error_date'] = isset($this->error['date']) ? $this->error['date'] : '';

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
			'href' => $this->url->link('extension/module/ocs_shell', $token, true)
		);

		$data['action'] = $this->url->link('extension/module/ocs_shell', $token, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=module', true);

		foreach ($this->globals as $field => $default) {
			$key = 'module_ocs_shell_' . $field;

			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} elseif ($this->config->has($key)) {
				$data[$key] = $this->config->get($key);
			} else {
				$data[$key] = $default;
			}
		}

		if (isset($this->request->post['module_ocs_shell_l'])) {
			$l = $this->request->post['module_ocs_shell_l'];
		} else {
			$l = (array)$this->config->get('module_ocs_shell_l');
		}

		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['l'] = array();

		foreach ($data['languages'] as $language) {
			foreach ($this->per_language as $field) {
				$data['l'][$language['code']][$field] = isset($l[$language['code']][$field]) ? $l[$language['code']][$field] : '';
			}
		}

		$data['version'] = '1.0.0';

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_shell', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/ocs_shell')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		foreach (array('announcement_from', 'announcement_to') as $field) {
			$value = isset($this->request->post['module_ocs_shell_' . $field]) ? $this->request->post['module_ocs_shell_' . $field] : '';

			if ($value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
				$this->error['date'] = $this->language->get('error_date');
			}
		}

		return !$this->error;
	}
}
