<?php
// Extension: ocs_back_in_stock. Subscription form and endpoint. Owner: SPEC-025 (REQ-QOR-020).
// Duplicates are answered with the same success message (no enumeration of subscribed e-mails).

class ControllerExtensionModuleOcsBackInStock extends Controller {
	public function form() {
		if (!$this->config->get('module_ocs_back_in_stock_status')) {
			$this->response->setOutput('');

			return;
		}

		$this->load->language('extension/module/ocs_back_in_stock');
		$this->load->model('extension/ocs_request/guard');

		$data['heading'] = $this->language->get('heading_form');
		$data['note'] = $this->language->get('text_note');
		$data['button'] = $this->language->get('button_submit');
		$data['text_close'] = $this->language->get('text_close');
		$data['failed'] = $this->language->get('error_generic');
		$data['action'] = $this->url->link('extension/module/ocs_back_in_stock/submit', '', true);
		$data['token'] = $this->model_extension_ocs_request_guard->token();
		$data['product_id'] = isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0;
		$data['fields'] = $this->model_extension_ocs_request_guard->fields(array('email' => 'required'), array('email' => $this->language->get('entry_email')));

		$this->response->setOutput($this->load->view('extension/ocs_request/form', $data));
	}

	public function submit() {
		$this->load->language('extension/module/ocs_back_in_stock');
		$this->load->model('extension/ocs_request/guard');

		$guard = $this->model_extension_ocs_request_guard;

		if (!$this->config->get('module_ocs_back_in_stock_status') || $this->request->server['REQUEST_METHOD'] != 'POST') {
			$guard->json(array('error' => $this->language->get('error_generic')));

			return;
		}

		if (!$guard->tokenValid(isset($this->request->post['token']) ? $this->request->post['token'] : '')) {
			$guard->json(array('error' => $this->language->get('error_token')));

			return;
		}

		if (!$guard->allow('back_in_stock')) {
			$guard->json(array('error' => $this->language->get('error_rate')));

			return;
		}

		$result = \Ocs\OrderRequest::validate($this->request->post, $guard->config(array('email' => 'required')));

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

		if (!$product || (int)$product['quantity'] > 0) {
			$guard->json(array('error' => $this->language->get('error_product')));

			return;
		}

		// chosen option values identify the variant; sorted so the same choice always gives the same key
		$ids = array();

		if (isset($this->request->post['option']) && is_array($this->request->post['option'])) {
			foreach ($this->request->post['option'] as $value) {
				foreach ((array)$value as $item) {
					if (is_scalar($item) && ctype_digit((string)$item)) {
						$ids[] = (int)$item;
					}
				}
			}
		}

		sort($ids);

		$option_key = substr(implode(',', $ids), 0, 64);

		$this->db->query("INSERT IGNORE INTO " . DB_PREFIX . "ocs_back_in_stock SET product_id = '" . $product_id . "', product_name = '" . $this->db->escape($product['name']) . "', option_key = '" . $this->db->escape($option_key) . "', email = '" . $this->db->escape(utf8_strtolower($result['clean']['email'])) . "', language_code = '" . $this->db->escape($this->config->get('config_language')) . "', status = '0', date_added = NOW()");

		$guard->json(array('success' => $this->language->get('text_success')));
	}
}
