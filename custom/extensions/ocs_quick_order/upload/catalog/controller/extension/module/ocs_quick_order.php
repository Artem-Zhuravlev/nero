<?php
// Extension: ocs_quick_order. One-click order: form popup body and submit endpoint. Owner: SPEC-025 (REQ-QOR-001..004).

class ControllerExtensionModuleOcsQuickOrder extends Controller {
	private $fields = array('name', 'phone', 'email', 'comment');

	/**
	 * GET ...&product_id=N: HTML body of the popup.
	 */
	public function form() {
		if (!$this->config->get('module_ocs_quick_order_status')) {
			$this->response->setOutput('');

			return;
		}

		$this->load->language('extension/module/ocs_quick_order');
		$this->load->model('extension/ocs_request/guard');

		$data['heading'] = $this->language->get('heading_form');
		$data['note'] = '';
		$data['button'] = $this->language->get('button_submit');
		$data['text_close'] = $this->language->get('text_close');
		$data['failed'] = $this->language->get('error_generic');
		$data['action'] = $this->url->link('extension/module/ocs_quick_order/submit', '', true);
		$data['token'] = $this->model_extension_ocs_request_guard->token();
		$data['product_id'] = isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0;
		$data['fields'] = $this->model_extension_ocs_request_guard->fields($this->modes(), $this->labels());

		$this->response->setOutput($this->load->view('extension/ocs_request/form', $data));
	}

	/**
	 * POST: validates, creates the order, answers JSON.
	 */
	public function submit() {
		$this->load->language('extension/module/ocs_quick_order');
		$this->load->model('extension/ocs_request/guard');

		$guard = $this->model_extension_ocs_request_guard;

		if (!$this->config->get('module_ocs_quick_order_status') || $this->request->server['REQUEST_METHOD'] != 'POST') {
			$guard->json(array('error' => $this->language->get('error_generic')));

			return;
		}

		if (!$guard->tokenValid(isset($this->request->post['token']) ? $this->request->post['token'] : '')) {
			$guard->json(array('error' => $this->language->get('error_token')));

			return;
		}

		if (!$guard->allow('quick_order')) {
			$guard->json(array('error' => $this->language->get('error_rate')));

			return;
		}

		$result = \Ocs\OrderRequest::validate($this->request->post, $guard->config($this->modes()));

		if (isset($result['errors']['spam'])) {
			$guard->json(array('error' => $this->language->get('error_generic')));

			return;
		}

		if ($result['errors']) {
			$messages = array();

			foreach ($result['errors'] as $field => $code) {
				$messages[$field] = $this->language->get('error_' . $code);
			}

			$guard->json(array('errors' => $messages));

			return;
		}

		$options = isset($this->request->post['option']) && is_array($this->request->post['option']) ? $this->request->post['option'] : array();
		$quantity = isset($this->request->post['quantity']) ? (int)$this->request->post['quantity'] : 1;
		$product_id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;

		$this->load->model('extension/ocs_request/order');

		try {
			$order_id = $this->model_extension_ocs_request_order->create($result['clean'], $product_id, $quantity, $options, $this->statusId(), $this->language->get('text_method'));
		} catch (\Exception $e) {
			$code = in_array($e->getMessage(), array('product', 'options', 'stock'), true) ? $e->getMessage() : 'generic';

			if ($code === 'generic') {
				$this->log->write('[ocs_quick_order] ' . $e->getMessage());
			}

			$guard->json(array('error' => $this->language->get('error_' . $code)));

			return;
		}

		// answer first, then confirm the order (core sends the order mails there)
		$guard->finish(array('success' => $this->language->get('text_success')));

		$this->model_extension_ocs_request_order->confirm($order_id, $this->statusId());
	}

	private function modes() {
		$defaults = array('name' => 'required', 'phone' => 'required', 'email' => 'off', 'comment' => 'optional');
		$modes = array();

		foreach ($this->fields as $field) {
			$value = $this->config->get('module_ocs_quick_order_' . $field);

			$modes[$field] = in_array($value, array('off', 'optional', 'required'), true) ? $value : $defaults[$field];
		}

		return $modes;
	}

	private function labels() {
		$labels = array();

		foreach ($this->fields as $field) {
			$labels[$field] = $this->language->get('entry_' . $field);
		}

		return $labels;
	}

	private function statusId() {
		$id = (int)$this->config->get('module_ocs_quick_order_order_status_id');

		return $id > 0 ? $id : (int)$this->config->get('config_order_status_id');
	}
}
