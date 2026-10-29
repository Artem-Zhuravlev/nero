<?php
// Extension: ocs_topbar. Admin settings: text, link and button of the bar above the header. Owner: SPEC-082.

class ControllerExtensionModuleOcsTopbar extends Controller {
	private $error = array();

	private $globals = array('status' => 0, 'bg' => 'accent', 'bg_custom' => '', 'from' => '', 'to' => '', 'closable' => 1, 'btn_style' => 'solid', 'blank' => 0);

	private $per_language = array('text', 'url', 'btn_label', 'btn_url');

	public function index() {
		$this->load->language('extension/module/ocs_topbar');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('localisation/language');

		$token = 'user_token=' . $this->session->data['user_token'];

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_ocs_topbar', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', $token . '&type=module', true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['error_date'] = isset($this->error['date']) ? $this->error['date'] : '';
		$data['error_color'] = isset($this->error['color']) ? $this->error['color'] : '';
		$data['error_button'] = isset($this->error['button']) ? $this->error['button'] : '';

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', $token, true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', $token . '&type=module', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/ocs_topbar', $token, true)
		);

		$data['action'] = $this->url->link('extension/module/ocs_topbar', $token, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=module', true);

		foreach ($this->globals as $field => $default) {
			$key = 'module_ocs_topbar_' . $field;

			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} elseif ($this->config->has($key)) {
				$data[$key] = $this->config->get($key);
			} else {
				$data[$key] = $default;
			}
		}

		if (isset($this->request->post['module_ocs_topbar_l'])) {
			$l = $this->request->post['module_ocs_topbar_l'];
		} else {
			$l = (array)$this->config->get('module_ocs_topbar_l');
		}

		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['l'] = array();

		foreach ($data['languages'] as $language) {
			foreach ($this->per_language as $field) {
				$data['l'][$language['code']][$field] = isset($l[$language['code']][$field]) ? $l[$language['code']][$field] : '';
			}
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_topbar', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/ocs_topbar')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		foreach (array('from', 'to') as $field) {
			$value = isset($this->request->post['module_ocs_topbar_' . $field]) ? $this->request->post['module_ocs_topbar_' . $field] : '';

			if ($value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
				$this->error['date'] = $this->language->get('error_date');
			}
		}

		$bg = isset($this->request->post['module_ocs_topbar_bg']) ? $this->request->post['module_ocs_topbar_bg'] : '';
		$hex = isset($this->request->post['module_ocs_topbar_bg_custom']) ? $this->request->post['module_ocs_topbar_bg_custom'] : '';

		if ($bg === 'custom' && !preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex)) {
			$this->error['color'] = $this->language->get('error_color');
		}

		// a button needs both a label and a link (REQ-TBR-004): half-filled pairs are reported instead of silently hidden
		if (isset($this->request->post['module_ocs_topbar_l']) && is_array($this->request->post['module_ocs_topbar_l'])) {
			foreach ($this->request->post['module_ocs_topbar_l'] as $fields) {
				$label = isset($fields['btn_label']) ? trim($fields['btn_label']) : '';
				$url = isset($fields['btn_url']) ? trim($fields['btn_url']) : '';

				if (($label === '') !== ($url === '')) {
					$this->error['button'] = $this->language->get('error_button');
				}

				foreach (array('url', 'btn_url') as $key) {
					$link = isset($fields[$key]) ? trim($fields[$key]) : '';

					if ($link !== '' && \Ocs\Shell::safeUrl($link) === '') {
						$this->error['button'] = $this->language->get('error_url');
					}
				}
			}
		}

		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('error_form');
		}

		return !$this->error;
	}
}
