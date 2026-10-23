<?php
// Extension: ocs_newsletter. Subscribe block, endpoint, confirm and unsubscribe pages. Owner: SPEC-033 (REQ-HOM-030).

class ControllerExtensionModuleOcsNewsletter extends Controller {
	public function index() {
		if (!$this->config->get('module_ocs_newsletter_status')) {
			return '';
		}

		$this->load->language('extension/module/ocs_newsletter');
		$this->load->model('extension/ocs_request/guard');

		$l = $this->texts();

		$data['heading_title'] = $l['title'] !== '' ? \Ocs\OrderRequest::e($l['title']) : $this->language->get('heading_title');
		$data['text'] = $l['text'] !== '' ? \Ocs\OrderRequest::e($l['text']) : $this->language->get('text_default');
		$data['consent'] = $l['consent'] !== '' ? \Ocs\OrderRequest::e($l['consent']) : $this->language->get('text_consent');
		$data['entry_email'] = $this->language->get('entry_email');
		$data['button_subscribe'] = $this->language->get('button_subscribe');
		$data['failed'] = $this->language->get('error_generic');
		$data['action'] = $this->url->link('extension/module/ocs_newsletter/subscribe', '', true);
		$data['token'] = $this->model_extension_ocs_request_guard->token();
		$data['email'] = $this->customer->isLogged() ? \Ocs\OrderRequest::e($this->customer->getEmail()) : '';

		return $this->load->view('extension/module/ocs_newsletter', $data);
	}

	/**
	 * POST: validates, stores, answers JSON. The answer is the same for a new and an already known address.
	 */
	public function subscribe() {
		$this->load->language('extension/module/ocs_newsletter');
		$this->load->model('extension/ocs_request/guard');

		$guard = $this->model_extension_ocs_request_guard;
		$post = $this->request->post;

		if (!$this->config->get('module_ocs_newsletter_status') || $this->request->server['REQUEST_METHOD'] != 'POST') {
			$guard->json(array('error' => $this->language->get('error_generic')));

			return;
		}

		if (!$guard->tokenValid(isset($post['token']) ? $post['token'] : '')) {
			$guard->json(array('error' => $this->language->get('error_token')));

			return;
		}

		if (!$guard->allow('newsletter')) {
			$guard->json(array('error' => $this->language->get('error_rate')));

			return;
		}

		// honeypot: bots fill the hidden "website" field and get a silent success
		if (!empty($post['website'])) {
			$guard->json(array('success' => $this->language->get('text_success')));

			return;
		}

		$email = \Ocs\Newsletter::email(isset($post['email']) ? $post['email'] : '');

		if ($email === '') {
			$guard->json(array('errors' => array('email' => $this->language->get('error_email'))));

			return;
		}

		if (empty($post['agree'])) {
			$guard->json(array('errors' => array('agree' => $this->language->get('error_agree'))));

			return;
		}

		$this->load->model('extension/ocs_newsletter/subscriber');

		$model = $this->model_extension_ocs_newsletter_subscriber;
		$double = (bool)$this->config->get('module_ocs_newsletter_double_optin');
		$own = $this->customer->isLogged() && strtolower($this->customer->getEmail()) === $email;

		// a signed-in customer using their own address: the core newsletter flag, no mail needed
		if ($own) {
			$model->setCustomerFlag($this->customer->getId());
		} else {
			$ip = isset($this->request->server['REMOTE_ADDR']) ? $this->request->server['REMOTE_ADDR'] : '';

			list($token, $confirmed, $is_new) = $model->save($email, (int)$this->config->get('config_language_id'), !$double, $ip);

			if ($double && !$confirmed && $is_new) {
				$this->sendConfirmation($email, $token);
			}
		}

		// hook for the integrations of SPEC-018 (mail providers); no handler = no effect
		$this->event->trigger('ocs/newsletter/subscribed', array($email, (int)$this->config->get('config_language_id'), !$double || $own));

		$guard->json(array('success' => $this->language->get($double && !$own ? 'text_success_confirm' : 'text_success')));
	}

	public function confirm() {
		$this->landing('confirm');
	}

	public function unsubscribe() {
		$this->landing('unsubscribe');
	}

	private function landing($action) {
		$this->load->language('extension/module/ocs_newsletter');
		$this->load->model('extension/ocs_newsletter/subscriber');

		$token = isset($this->request->get['token']) ? $this->request->get['token'] : '';
		$done = \Ocs\Newsletter::validToken($token) && ($action == 'confirm' ? $this->model_extension_ocs_newsletter_subscriber->confirm($token) : $this->model_extension_ocs_newsletter_subscriber->remove($token));

		$this->document->setTitle($this->language->get('heading_title'));
		$this->document->addLink($this->url->link('common/home'), 'canonical');

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_message'] = $this->language->get($done ? 'text_' . $action . '_done' : 'error_link');
		$data['continue'] = $this->url->link('common/home');
		$data['button_continue'] = $this->language->get('button_continue');
		$data['breadcrumbs'] = array();
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->addHeader('X-Robots-Tag: noindex');
		$this->response->setOutput($this->load->view('common/success', $data));
	}

	private function sendConfirmation($email, $token) {
		$link = $this->url->link('extension/module/ocs_newsletter/confirm', 'token=' . $token, true);

		try {
			$mail = new Mail($this->config->get('config_mail_engine'));
			$mail->parameter = $this->config->get('config_mail_parameter');
			$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
			$mail->smtp_username = $this->config->get('config_mail_smtp_username');
			$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
			$mail->smtp_port = $this->config->get('config_mail_smtp_port');
			$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

			$mail->setTo($email);
			$mail->setFrom($this->config->get('config_email'));
			$mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
			$mail->setSubject(html_entity_decode($this->language->get('mail_subject'), ENT_QUOTES, 'UTF-8'));
			$mail->setText(sprintf($this->language->get('mail_text'), $link));
			$mail->send();
		} catch (\Exception $e) {
			$this->log->write('[ocs_newsletter] ' . $e->getMessage());
		}
	}

	private function texts() {
		$all = (array)$this->config->get('module_ocs_newsletter_l');
		$one = isset($all[$this->config->get('config_language')]) && is_array($all[$this->config->get('config_language')]) ? $all[$this->config->get('config_language')] : array();

		return array(
			'title'   => isset($one['title']) ? trim(html_entity_decode((string)$one['title'], ENT_QUOTES, 'UTF-8')) : '',
			'text'    => isset($one['text']) ? trim(html_entity_decode((string)$one['text'], ENT_QUOTES, 'UTF-8')) : '',
			'consent' => isset($one['consent']) ? trim(html_entity_decode((string)$one['consent'], ENT_QUOTES, 'UTF-8')) : ''
		);
	}
}
