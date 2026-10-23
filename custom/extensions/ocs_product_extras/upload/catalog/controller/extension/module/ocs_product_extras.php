<?php
// Extension: ocs_product_extras. Question endpoint of the product page. Owner: SPEC-035 (REQ-PRD-010).

class ControllerExtensionModuleOcsProductExtras extends Controller {
	/**
	 * POST product_id, name, email, text, token, website (honeypot): the question waits for moderation.
	 */
	public function ask() {
		$this->load->language('extension/module/ocs_product_extras');
		$this->load->model('extension/ocs_request/guard');

		$guard = $this->model_extension_ocs_request_guard;
		$post = $this->request->post;

		if (!$this->config->get('module_ocs_product_extras_status') || !$this->config->get('module_ocs_product_extras_questions') || $this->request->server['REQUEST_METHOD'] != 'POST') {
			$guard->json(array('error' => $this->language->get('error_generic')));

			return;
		}

		if (!$guard->tokenValid(isset($post['token']) ? $post['token'] : '')) {
			$guard->json(array('error' => $this->language->get('error_token')));

			return;
		}

		if (!$guard->allow('question')) {
			$guard->json(array('error' => $this->language->get('error_rate')));

			return;
		}

		if (!empty($post['website'])) {
			$guard->json(array('success' => $this->language->get('text_question_sent')));

			return;
		}

		$this->load->model('catalog/product');

		$product_id = isset($post['product_id']) ? (int)$post['product_id'] : 0;

		if (!$this->model_catalog_product->getProduct($product_id)) {
			$guard->json(array('error' => $this->language->get('error_generic')));

			return;
		}

		// core request cleaning entity-encodes the input; the table keeps the raw text and every output escapes it once
		foreach (array('name', 'email', 'text') as $field) {
			$post[$field] = isset($post[$field]) ? html_entity_decode((string)$post[$field], ENT_QUOTES, 'UTF-8') : '';
		}

		$result = \Ocs\ProductExtras::validateQuestion($post);

		if ($result['errors']) {
			$messages = array();

			foreach ($result['errors'] as $field => $code) {
				$messages[$field] = $this->language->get('error_q_' . $code);
			}

			$guard->json(array('errors' => $messages));

			return;
		}

		$this->load->model('extension/ocs_product_extras/extras');

		$this->model_extension_ocs_product_extras_extras->addQuestion($product_id, $this->customer->isLogged() ? $this->customer->getId() : 0, $result['clean'], isset($this->request->server['REMOTE_ADDR']) ? $this->request->server['REMOTE_ADDR'] : '');

		$guard->json(array('success' => $this->language->get('text_question_sent')));
	}
}
