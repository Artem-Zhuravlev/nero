<?php
// Extension: ocs_seo_text. Admin: status and the text per language. Owner: SPEC-033 (REQ-HOM-040).

class ControllerExtensionModuleOcsSeoText extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/ocs_seo_text');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('localisation/language');

		$token = 'user_token=' . $this->session->data['user_token'];

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_ocs_seo_text', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', $token . '&type=module', true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $token . '&type=module', true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/ocs_seo_text', $token, true));

		$data['action'] = $this->url->link('extension/module/ocs_seo_text', $token, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=module', true);

		$data['module_ocs_seo_text_status'] = isset($this->request->post['module_ocs_seo_text_status']) ? $this->request->post['module_ocs_seo_text_status'] : $this->config->get('module_ocs_seo_text_status');

		$l = isset($this->request->post['module_ocs_seo_text_l']) ? $this->request->post['module_ocs_seo_text_l'] : (array)$this->config->get('module_ocs_seo_text_l');

		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['l'] = array();

		foreach ($data['languages'] as $language) {
			$data['l'][$language['code']] = array(
				'title' => isset($l[$language['code']]['title']) ? $l[$language['code']]['title'] : '',
				'text'  => isset($l[$language['code']]['text']) ? $l[$language['code']]['text'] : ''
			);
		}

		$data['version'] = '1.0.0';

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_seo_text', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/ocs_seo_text')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
