<?php
// Extension: ocs_quick_view. Admin: the on/off switch. Owner: SPEC-037 (REQ-QVW-003).

class ControllerExtensionModuleOcsQuickView extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/ocs_quick_view');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		$token = 'user_token=' . $this->session->data['user_token'];

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_ocs_quick_view', array('module_ocs_quick_view_status' => !empty($this->request->post['module_ocs_quick_view_status']) ? 1 : 0));

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', $token . '&type=module', true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $token . '&type=module', true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/ocs_quick_view', $token, true));

		$data['action'] = $this->url->link('extension/module/ocs_quick_view', $token, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=module', true);
		$data['status'] = isset($this->request->post['module_ocs_quick_view_status']) ? (int)$this->request->post['module_ocs_quick_view_status'] : (int)$this->config->get('module_ocs_quick_view_status');
		$data['version'] = '1.0.0';

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_quick_view', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/ocs_quick_view')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
