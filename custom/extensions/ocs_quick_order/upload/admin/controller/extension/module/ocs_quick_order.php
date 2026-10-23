<?php
// Extension: ocs_quick_order. Admin settings: fields, initial order status. Owner: SPEC-025 (REQ-QOR-001, 004).

class ControllerExtensionModuleOcsQuickOrder extends Controller {
	private $error = array();

	private $defaults = array('status' => 0, 'name' => 'required', 'phone' => 'required', 'email' => 'off', 'comment' => 'optional', 'order_status_id' => 1);

	public function index() {
		$this->load->language('extension/module/ocs_quick_order');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('localisation/order_status');

		$token = 'user_token=' . $this->session->data['user_token'];

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_ocs_quick_order', $this->request->post);

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
			'href' => $this->url->link('extension/module/ocs_quick_order', $token, true)
		);

		$data['action'] = $this->url->link('extension/module/ocs_quick_order', $token, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=module', true);

		foreach ($this->defaults as $field => $default) {
			$key = 'module_ocs_quick_order_' . $field;

			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} elseif ($this->config->has($key)) {
				$data[$key] = $this->config->get($key);
			} else {
				$data[$key] = $default;
			}
		}

		$data['fields'] = array('name', 'phone', 'email', 'comment');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
		$data['version'] = '1.0.0';

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_quick_order', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/ocs_quick_order')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		foreach (array('name', 'phone', 'email', 'comment') as $field) {
			$value = isset($this->request->post['module_ocs_quick_order_' . $field]) ? $this->request->post['module_ocs_quick_order_' . $field] : '';

			if (!in_array($value, array('off', 'optional', 'required'), true)) {
				$this->error['warning'] = $this->language->get('error_values');
			}
		}

		if (empty($this->request->post['module_ocs_quick_order_phone']) || $this->request->post['module_ocs_quick_order_phone'] == 'off') {
			$this->error['warning'] = $this->language->get('error_phone');
		}

		return !$this->error;
	}
}
