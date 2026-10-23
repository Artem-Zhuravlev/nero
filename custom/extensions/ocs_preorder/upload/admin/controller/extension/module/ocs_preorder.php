<?php
// Extension: ocs_preorder. Admin settings and the request list. Owner: SPEC-025 (REQ-QOR-010..013).

class ControllerExtensionModuleOcsPreorder extends Controller {
	private $error = array();

	private $defaults = array(
		'status' => 0, 'global' => 1, 'name' => 'required', 'phone' => 'required', 'email' => 'optional', 'comment' => 'optional',
		'notify_admin' => 1, 'create_order' => 0, 'order_status_id' => 1, 'overrides' => ''
	);

	public function index() {
		$this->load->language('extension/module/ocs_preorder');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('localisation/order_status');
		$this->load->model('localisation/language');

		$token = 'user_token=' . $this->session->data['user_token'];

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_ocs_preorder', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', $token . '&type=module', true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $token . '&type=module', true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/ocs_preorder', $token, true));

		$data['action'] = $this->url->link('extension/module/ocs_preorder', $token, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=module', true);
		$data['requests'] = $this->url->link('extension/module/ocs_preorder/requests', $token, true);

		foreach ($this->defaults as $field => $default) {
			$key = 'module_ocs_preorder_' . $field;

			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} elseif ($this->config->has($key)) {
				$data[$key] = $this->config->get($key);
			} else {
				$data[$key] = $default;
			}
		}

		$l = isset($this->request->post['module_ocs_preorder_l']) ? $this->request->post['module_ocs_preorder_l'] : (array)$this->config->get('module_ocs_preorder_l');

		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['l'] = array();

		foreach ($data['languages'] as $language) {
			$data['l'][$language['code']] = array(
				'button'  => isset($l[$language['code']]['button']) ? $l[$language['code']]['button'] : '',
				'message' => isset($l[$language['code']]['message']) ? $l[$language['code']]['message'] : ''
			);
		}

		$data['fields'] = array('name', 'phone', 'email', 'comment');
		// core subtracts stock for processing/complete statuses: they are not offered for pre-orders
		$blocked = array_map('intval', array_merge((array)$this->config->get('config_processing_status'), (array)$this->config->get('config_complete_status')));

		$data['order_statuses'] = array();

		foreach ($this->model_localisation_order_status->getOrderStatuses() as $status) {
			if (!in_array((int)$status['order_status_id'], $blocked, true)) {
				$data['order_statuses'][] = $status;
			}
		}
		$data['version'] = '1.0.0';

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_preorder', $data));
	}

	/**
	 * Request list (newest first, 50 per page).
	 */
	public function requests() {
		$this->load->language('extension/module/ocs_preorder');

		$this->document->setTitle($this->language->get('heading_requests'));

		$this->load->model('extension/ocs_preorder/request');

		$token = 'user_token=' . $this->session->data['user_token'];
		$page = isset($this->request->get['page']) ? max(1, (int)$this->request->get['page']) : 1;

		$data['success'] = '';

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			if (!$this->user->hasPermission('modify', 'extension/module/ocs_preorder')) {
				$data['error_warning'] = $this->language->get('error_permission');
			} elseif (isset($this->request->post['preorder_id'])) {
				if (isset($this->request->post['delete'])) {
					$this->model_extension_ocs_preorder_request->delete((int)$this->request->post['preorder_id']);
				} elseif (isset($this->request->post['status'])) {
					$this->model_extension_ocs_preorder_request->setStatus((int)$this->request->post['preorder_id'], (int)$this->request->post['status']);
				}

				$data['success'] = $this->language->get('text_saved');
			}
		}

		$data['error_warning'] = isset($data['error_warning']) ? $data['error_warning'] : '';

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('heading_requests'), 'href' => $this->url->link('extension/module/ocs_preorder/requests', $token, true));

		$data['requests'] = array();

		foreach ($this->model_extension_ocs_preorder_request->getRequests(($page - 1) * 50, 50) as $row) {
			$data['requests'][] = array(
				'preorder_id'   => $row['preorder_id'],
				'date'          => date($this->language->get('datetime_format'), strtotime($row['date_added'])),
				'product'       => $row['product_name'],
				'product_href'  => $this->url->link('catalog/product/edit', $token . '&product_id=' . (int)$row['product_id'], true),
				'quantity'      => $row['quantity'],
				'options'       => $row['options'],
				'name'          => $row['name'],
				'phone'         => $row['phone'],
				'email'         => $row['email'],
				'comment'       => $row['comment'],
				'expected_date' => $row['expected_date'],
				'order_id'      => $row['order_id'],
				'order_href'    => $row['order_id'] ? $this->url->link('sale/order/info', $token . '&order_id=' . (int)$row['order_id'], true) : '',
				'status'        => (int)$row['status']
			);
		}

		$pagination = new Pagination();
		$pagination->total = $this->model_extension_ocs_preorder_request->getTotal();
		$pagination->page = $page;
		$pagination->limit = 50;
		$pagination->url = $this->url->link('extension/module/ocs_preorder/requests', $token . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['action'] = $this->url->link('extension/module/ocs_preorder/requests', $token . '&page=' . $page, true);
		$data['back'] = $this->url->link('extension/module/ocs_preorder', $token, true);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_preorder_requests', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/ocs_preorder')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		foreach (array('name', 'phone', 'email', 'comment') as $field) {
			$value = isset($this->request->post['module_ocs_preorder_' . $field]) ? $this->request->post['module_ocs_preorder_' . $field] : '';

			if (!in_array($value, array('off', 'optional', 'required'), true)) {
				$this->error['warning'] = $this->language->get('error_values');
			}
		}

		if (isset($this->request->post['module_ocs_preorder_phone']) && $this->request->post['module_ocs_preorder_phone'] == 'off') {
			$this->error['warning'] = $this->language->get('error_phone');
		}

		return !$this->error;
	}
}
