<?php
// Extension: ocs_newsletter. Admin: settings, subscriber list, CSV export, delete. Owner: SPEC-033 (REQ-HOM-030).

class ControllerExtensionModuleOcsNewsletter extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/ocs_newsletter');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('localisation/language');
		$this->load->model('extension/ocs_newsletter/subscriber');

		$token = 'user_token=' . $this->session->data['user_token'];
		$data['success'] = '';

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			if (!$this->user->hasPermission('modify', 'extension/module/ocs_newsletter')) {
				$this->error['warning'] = $this->language->get('error_permission');
			} elseif (isset($this->request->post['delete_id'])) {
				$this->model_extension_ocs_newsletter_subscriber->delete((int)$this->request->post['delete_id']);

				$data['success'] = $this->language->get('text_deleted');
			} else {
				$this->model_setting_setting->editSetting('module_ocs_newsletter', $this->request->post);

				$data['success'] = $this->language->get('text_success');
			}
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $token . '&type=module', true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/ocs_newsletter', $token, true));

		$data['action'] = $this->url->link('extension/module/ocs_newsletter', $token, true);
		$data['export'] = $this->url->link('extension/module/ocs_newsletter/export', $token, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=module', true);

		$data['module_ocs_newsletter_status'] = isset($this->request->post['module_ocs_newsletter_status']) ? $this->request->post['module_ocs_newsletter_status'] : $this->config->get('module_ocs_newsletter_status');
		$data['module_ocs_newsletter_double_optin'] = isset($this->request->post['module_ocs_newsletter_double_optin']) ? $this->request->post['module_ocs_newsletter_double_optin'] : $this->config->get('module_ocs_newsletter_double_optin');

		$l = isset($this->request->post['module_ocs_newsletter_l']) ? $this->request->post['module_ocs_newsletter_l'] : (array)$this->config->get('module_ocs_newsletter_l');

		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['l'] = array();

		foreach ($data['languages'] as $language) {
			foreach (array('title', 'text', 'consent') as $field) {
				$data['l'][$language['code']][$field] = isset($l[$language['code']][$field]) ? $l[$language['code']][$field] : '';
			}
		}

		$page = isset($this->request->get['page']) ? max(1, (int)$this->request->get['page']) : 1;
		$limit = 20;

		$data['subscribers'] = array();

		foreach ($this->model_extension_ocs_newsletter_subscriber->getSubscribers(($page - 1) * $limit, $limit) as $row) {
			$data['subscribers'][] = array(
				'subscriber_id' => $row['subscriber_id'],
				'email'         => $row['email'],
				'confirmed'     => $row['confirmed'],
				'date_added'    => $row['date_added']
			);
		}

		$total = $this->model_extension_ocs_newsletter_subscriber->getTotal();

		$pagination = new Pagination();
		$pagination->total = $total;
		$pagination->page = $page;
		$pagination->limit = $limit;
		$pagination->url = $this->url->link('extension/module/ocs_newsletter', $token . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['total'] = $total;
		$data['version'] = '1.0.0';

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_newsletter', $data));
	}

	/**
	 * CSV of the confirmed addresses.
	 */
	public function export() {
		$this->load->language('extension/module/ocs_newsletter');

		if (!$this->user->hasPermission('modify', 'extension/module/ocs_newsletter')) {
			$this->session->data['error'] = $this->language->get('error_permission');

			$this->response->redirect($this->url->link('extension/module/ocs_newsletter', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->load->model('extension/ocs_newsletter/subscriber');

		$csv = \Ocs\Newsletter::csvRow(array('email', 'language_id', 'date_added'));

		foreach ($this->model_extension_ocs_newsletter_subscriber->getConfirmed() as $row) {
			$csv .= \Ocs\Newsletter::csvRow(array($row['email'], $row['language_id'], $row['date_added']));
		}

		$this->response->addHeader('Content-Type: text/csv; charset=utf-8');
		$this->response->addHeader('Content-Disposition: attachment; filename="subscribers.csv"');
		$this->response->setOutput($csv);
	}
}
