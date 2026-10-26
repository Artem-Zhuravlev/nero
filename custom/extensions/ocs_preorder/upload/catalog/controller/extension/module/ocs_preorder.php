<?php
// Extension: ocs_preorder. Pre-order form popup body and submit endpoint. Owner: SPEC-025 (REQ-QOR-010..013).
// A pre-order never decrements stock and never becomes a normal purchase: the optional order is created only with a status
// that is neither "processing" nor "complete" (core subtracts stock for those).

class ControllerExtensionModuleOcsPreorder extends Controller {
	private $fields = array('name', 'phone', 'email', 'comment');

	/**
	 * GET ...&product_id=N: HTML body of the popup.
	 */
	public function form() {
		if (!$this->config->get('module_ocs_preorder_status')) {
			$this->response->setOutput('');

			return;
		}

		$this->load->language('extension/module/ocs_preorder');
		$this->load->model('extension/ocs_request/guard');

		$l = $this->texts();

		$data['heading'] = !empty($l['button']) ? \Ocs\OrderRequest::e($l['button']) : $this->language->get('button_preorder');
		$data['note'] = !empty($l['message']) ? \Ocs\OrderRequest::e($l['message']) : $this->language->get('text_message');
		$data['button'] = $this->language->get('button_submit');
		$data['text_close'] = $this->language->get('text_close');
		$data['failed'] = $this->language->get('error_generic');
		$data['action'] = $this->url->link('extension/module/ocs_preorder/submit', '', true);
		$data['token'] = $this->model_extension_ocs_request_guard->token();
		$data['product_id'] = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : (isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0);
		$data['fields'] = $this->model_extension_ocs_request_guard->fields($this->modes(), $this->labels());

		$this->response->setOutput($this->load->view('extension/ocs_request/form', $data));
	}

	/**
	 * POST: validates, stores the request (and optionally an order), answers JSON.
	 */
	public function submit() {
		$this->load->language('extension/module/ocs_preorder');
		$this->load->model('extension/ocs_request/guard');

		$guard = $this->model_extension_ocs_request_guard;

		if (!$this->config->get('module_ocs_preorder_status') || $this->request->server['REQUEST_METHOD'] != 'POST') {
			$guard->json(array('error' => $this->language->get('error_generic')));

			return;
		}

		if (!$guard->tokenValid(isset($this->request->post['token']) ? $this->request->post['token'] : '')) {
			$guard->json(array('error' => $this->language->get('error_token')));

			return;
		}

		if (!$guard->allow('preorder')) {
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

		$product_id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;

		$this->load->model('catalog/product');

		$product = $this->model_catalog_product->getProduct($product_id);

		$overrides = \Ocs\OrderRequest::parseOverrides($this->config->get('module_ocs_preorder_overrides'));
		$override = isset($overrides[$product_id]) ? $overrides[$product_id] : array();

		$this->load->model('extension/ocs_request/options');

		$post_options = isset($this->request->post['option']) && is_array($this->request->post['option']) ? $this->request->post['option'] : array();

		if (!$product || !\Ocs\Stock::preorderAppliesFor($this->config->get('module_ocs_preorder_global'), $product['quantity'], $override, \Ocs\Stock::selectedValueIds($post_options), $this->model_extension_ocs_request_options->outValues($product_id))) {
			$guard->json(array('error' => $this->language->get('error_product')));

			return;
		}

		$options = isset($this->request->post['option']) && is_array($this->request->post['option']) ? $this->request->post['option'] : array();

		foreach ($this->model_catalog_product->getProductOptions($product_id) as $product_option) {
			if ($product_option['required'] && empty($options[$product_option['product_option_id']])) {
				$guard->json(array('error' => $this->language->get('error_options')));

				return;
			}
		}

		$quantity = max(1, isset($this->request->post['quantity']) ? (int)$this->request->post['quantity'] : 1, (int)$product['minimum']);

		$order_id = 0;

		if ($this->config->get('module_ocs_preorder_create_order') && $this->safeStatus()) {
			$this->load->model('extension/ocs_request/order');

			try {
				$order_id = $this->model_extension_ocs_request_order->create($result['clean'], $product_id, $quantity, $options, $this->safeStatus(), $this->language->get('text_method'), false);
			} catch (\Exception $e) {
				$this->log->write('[ocs_preorder] order not created: ' . $e->getMessage());
			}
		}

		$this->db->query("INSERT INTO " . DB_PREFIX . "ocs_preorder SET product_id = '" . $product_id . "', product_name = '" . $this->db->escape($product['name']) . "', quantity = '" . $quantity . "', options = '" . $this->db->escape($this->describeOptions($product_id, $options)) . "', name = '" . $this->db->escape($result['clean']['name']) . "', phone = '" . $this->db->escape($result['clean']['phone']) . "', email = '" . $this->db->escape($result['clean']['email']) . "', comment = '" . $this->db->escape($result['clean']['comment']) . "', expected_date = '" . $this->db->escape(isset($override['date']) ? $override['date'] : '') . "', order_id = '" . (int)$order_id . "', status = '0', date_added = NOW()");

		// answer first, then the slow parts (order mails, notification mails)
		$guard->finish(array('success' => $this->language->get('text_success')));

		if ($order_id) {
			$this->model_extension_ocs_request_order->confirm($order_id, $this->safeStatus());
		}

		$this->notify($product, $result['clean'], $quantity);
	}

	private function notify($product, $contact, $quantity) {
		if (!$this->config->get('config_mail_engine')) {
			return;
		}

		try {
			$subject = sprintf($this->language->get('mail_subject'), $product['name']);
			$text = sprintf($this->language->get('mail_text'), $product['name'], $quantity, $contact['name'], $contact['phone'], $contact['email'], $contact['comment']);

			if ($this->config->get('module_ocs_preorder_notify_admin')) {
				$this->send($this->config->get('config_email'), $subject, $text);
			}

			if ($contact['email']) {
				$this->send($contact['email'], $subject, $this->language->get('text_success'));
			}
		} catch (\Exception $e) {
			$this->log->write('[ocs_preorder] mail failed: ' . $e->getMessage());
		}
	}

	private function send($to, $subject, $text) {
		$mail = new Mail($this->config->get('config_mail_engine'));
		$mail->parameter = $this->config->get('config_mail_parameter');
		$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
		$mail->smtp_username = $this->config->get('config_mail_smtp_username');
		$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
		$mail->smtp_port = $this->config->get('config_mail_smtp_port');
		$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

		$mail->setTo($to);
		$mail->setFrom($this->config->get('config_email'));
		$mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
		$mail->setSubject(html_entity_decode($subject, ENT_QUOTES, 'UTF-8'));
		$mail->setText($text);
		$mail->send();
	}

	/**
	 * Human readable option list for the admin ("Size: L; Color: Red").
	 */
	private function describeOptions($product_id, $options) {
		$parts = array();

		foreach ($this->model_catalog_product->getProductOptions($product_id) as $product_option) {
			$id = $product_option['product_option_id'];

			if (!isset($options[$id]) || $options[$id] === '' || $options[$id] === array()) {
				continue;
			}

			$chosen = (array)$options[$id];
			$values = array();

			if (!empty($product_option['product_option_value'])) {
				foreach ($product_option['product_option_value'] as $value) {
					if (in_array($value['product_option_value_id'], $chosen)) {
						$values[] = $value['name'];
					}
				}
			} else {
				$values = array_map('strval', $chosen);
			}

			if ($values) {
				$parts[] = $product_option['name'] . ': ' . implode(', ', $values);
			}
		}

		return implode('; ', $parts);
	}

	/**
	 * Order status that is not processing/complete (core would subtract stock for those); 0 means "do not create an order".
	 */
	private function safeStatus() {
		$id = (int)$this->config->get('module_ocs_preorder_order_status_id');

		if ($id < 1) {
			return 0;
		}

		$blocked = array_merge((array)$this->config->get('config_processing_status'), (array)$this->config->get('config_complete_status'));

		return in_array($id, array_map('intval', $blocked), true) ? 0 : $id;
	}

	private function modes() {
		$defaults = array('name' => 'required', 'phone' => 'required', 'email' => 'optional', 'comment' => 'optional');
		$modes = array();

		foreach ($this->fields as $field) {
			$value = $this->config->get('module_ocs_preorder_' . $field);

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

	private function texts() {
		$l = (array)$this->config->get('module_ocs_preorder_l');
		$code = $this->config->get('config_language');

		return isset($l[$code]) && is_array($l[$code]) ? $l[$code] : array();
	}
}
