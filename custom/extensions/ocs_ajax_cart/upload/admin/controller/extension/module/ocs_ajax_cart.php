<?php
// Extension: ocs_ajax_cart. Admin settings: status and the "few left" threshold. Owner: SPEC-026.

class ControllerExtensionModuleOcsAjaxCart extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/ocs_ajax_cart');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		$token = 'user_token=' . $this->session->data['user_token'];

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_ocs_ajax_cart', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', $token . '&type=module', true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $token . '&type=module', true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/ocs_ajax_cart', $token, true));

		$data['action'] = $this->url->link('extension/module/ocs_ajax_cart', $token, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=module', true);

		foreach (array('status' => 0, 'low_stock' => 5) as $field => $default) {
			$key = 'module_ocs_ajax_cart_' . $field;

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

		$this->response->setOutput($this->load->view('extension/module/ocs_ajax_cart', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/ocs_ajax_cart')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$low = isset($this->request->post['module_ocs_ajax_cart_low_stock']) ? $this->request->post['module_ocs_ajax_cart_low_stock'] : '';

		if (!ctype_digit((string)$low) || (int)$low > 1000) {
			$this->error['warning'] = $this->language->get('error_values');
		}

		return !$this->error;
	}
}
