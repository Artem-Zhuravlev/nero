<?php
// Extension: ocs_tradein. Trade-in request popup body and submit endpoint. Owner: SPEC-036 (REQ-CRD-010, 020).

class ControllerExtensionModuleOcsTradein extends Controller {
	/**
	 * GET ...&product_id=N: HTML body of the popup (same class names as the shared request form, so OCS.req binds it).
	 */
	public function form() {
		if (!$this->config->get('module_ocs_tradein_status')) {
			$this->response->setOutput('');

			return;
		}

		$this->load->language('extension/module/ocs_tradein');
		$this->load->model('extension/ocs_request/guard');

		foreach (array('heading_form', 'text_note', 'entry_name', 'entry_phone', 'entry_email', 'entry_device', 'entry_photos', 'help_photos', 'button_submit', 'text_close', 'error_generic') as $key) {
			$data[$key] = $this->language->get($key);
		}

		$data['action'] = $this->url->link('extension/module/ocs_tradein/submit', '', true);
		$data['token'] = $this->model_extension_ocs_request_guard->token();
		$data['product_id'] = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : (isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0);

		$this->response->setOutput($this->load->view('extension/module/ocs_tradein_form', $data));
	}

	/**
	 * POST (multipart): validates, stores the photos outside the docroot, saves the lead, mails the manager.
	 */
	public function submit() {
		$this->load->language('extension/module/ocs_tradein');
		$this->load->model('extension/ocs_request/guard');

		$guard = $this->model_extension_ocs_request_guard;
		$post = $this->request->post;

		if (!$this->config->get('module_ocs_tradein_status') || $this->request->server['REQUEST_METHOD'] != 'POST') {
			$guard->json(array('error' => $this->language->get('error_generic')));

			return;
		}

		if (!$guard->tokenValid(isset($post['token']) ? $post['token'] : '')) {
			$guard->json(array('error' => $this->language->get('error_token')));

			return;
		}

		if (!$guard->allow('tradein')) {
			$guard->json(array('error' => $this->language->get('error_rate')));

			return;
		}

		// core request cleaning entity-encodes the input; the lead keeps the raw text and every output escapes it once
		foreach (array('name', 'phone', 'email', 'comment') as $field) {
			$post[$field] = isset($post[$field]) ? html_entity_decode((string)$post[$field], ENT_QUOTES, 'UTF-8') : '';
		}

		$result = \Ocs\OrderRequest::validate($post, array(
			'name'    => array('enabled' => true, 'required' => false),
			'phone'   => array('enabled' => true, 'required' => true),
			'email'   => array('enabled' => true, 'required' => false),
			'comment' => array('enabled' => true, 'required' => true)
		));

		if (isset($result['errors']['spam'])) {
			$guard->json(array('error' => $this->language->get('error_generic')));

			return;
		}

		$errors = array();

		foreach ($result['errors'] as $field => $code) {
			$errors[$field] = $this->language->get('error_' . $code);
		}

		// the form calls the device description "comment" for the shared validator and "device" for the visitor
		if (isset($errors['comment'])) {
			$errors['comment'] = $this->language->get('error_device');
		}

		$entries = isset($this->request->files['photos']) ? \Ocs\Tradein::normalize($this->request->files['photos']) : array();
		$uploads = \Ocs\Tradein::check($entries);

		if ($uploads['error'] !== '') {
			$errors['photos'] = $this->language->get('error_photo_' . $uploads['error']);
		}

		if ($errors) {
			$guard->json(array('errors' => $errors));

			return;
		}

		$stored = array();
		$dir = $this->dir();

		foreach ($uploads['files'] as $file) {
			$name = \Ocs\Tradein::storedName($file['ext']);

			if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
				$this->log->write('[ocs_tradein] cannot create ' . $dir);

				$guard->json(array('error' => $this->language->get('error_generic')));

				return;
			}

			if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
				$guard->json(array('error' => $this->language->get('error_generic')));

				return;
			}

			$stored[] = $name;
		}

		$this->load->model('extension/ocs_tradein/lead');

		$product_id = isset($post['product_id']) ? (int)$post['product_id'] : 0;
		$lead_id = $this->model_extension_ocs_tradein_lead->add($product_id, $result['clean'], $stored, isset($this->request->server['REMOTE_ADDR']) ? $this->request->server['REMOTE_ADDR'] : '');

		// answer first, then the (possibly slow) manager mail
		$guard->finish(array('success' => $this->language->get('text_success')));

		$this->notify($lead_id, $result['clean'], count($stored), $product_id);
	}

	private function dir() {
		return rtrim(DIR_STORAGE, '/\\') . '/ocs_tradein';
	}

	private function notify($lead_id, $clean, $photos, $product_id) {
		$to = trim((string)$this->config->get('module_ocs_tradein_email'));
		$to = filter_var($to, FILTER_VALIDATE_EMAIL) ? $to : $this->config->get('config_email');

		try {
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
			$mail->setSubject('Trade-in #' . (int)$lead_id);
			$mail->setText(implode("\n", array(
				'Trade-in #' . (int)$lead_id,
				'Product: ' . ($product_id ? html_entity_decode($this->url->link('product/product', 'product_id=' . (int)$product_id), ENT_QUOTES, 'UTF-8') : '-'),
				'Name: ' . $clean['name'],
				'Phone: ' . $clean['phone'],
				'E-mail: ' . $clean['email'],
				'Device: ' . $clean['comment'],
				'Photos: ' . (int)$photos . ' (see the admin panel)'
			)));
			$mail->send();
		} catch (\Exception $e) {
			$this->log->write('[ocs_tradein] ' . $e->getMessage());
		}
	}
}
