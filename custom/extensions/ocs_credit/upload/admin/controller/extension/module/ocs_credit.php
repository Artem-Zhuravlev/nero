<?php
// Extension: ocs_credit. Admin: status, plans, texts per language. Owner: SPEC-036 (REQ-CRD-001, 002).

class ControllerExtensionModuleOcsCredit extends Controller {
	private $error = array();
	private $slots = 8;

	public function index() {
		$this->load->language('extension/module/ocs_credit');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('localisation/language');

		$token = 'user_token=' . $this->session->data['user_token'];
		$data['success'] = '';
		$posted = $this->request->server['REQUEST_METHOD'] == 'POST';
		$rows = null;

		if ($posted) {
			if (!$this->user->hasPermission('modify', 'extension/module/ocs_credit')) {
				$this->error['warning'] = $this->language->get('error_permission');
			} else {
				$post = $this->decode($this->request->post);
				$result = \Ocs\Credit::cleanPlans(isset($post['plan']) ? $post['plan'] : array());

				foreach ($result['errors'] as $code) {
					$this->error[$code] = $this->language->get('error_' . $code);
				}

				if (!$this->error) {
					$this->model_setting_setting->editSetting('module_ocs_credit', array(
						'module_ocs_credit_status' => !empty($post['module_ocs_credit_status']) ? 1 : 0,
						'module_ocs_credit_plans'  => $result['plans'],
						'module_ocs_credit_l'      => isset($post['module_ocs_credit_l']) && is_array($post['module_ocs_credit_l']) ? $post['module_ocs_credit_l'] : array()
					));

					$data['success'] = $this->language->get('text_success');
				} else {
					$rows = isset($post['plan']) ? $post['plan'] : array();
				}
			}
		}

		$data['errors'] = array_values(array_filter($this->error));
		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $token . '&type=module', true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/ocs_credit', $token, true));

		$data['action'] = $this->url->link('extension/module/ocs_credit', $token, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=module', true);
		$data['status'] = $posted && isset($this->request->post['module_ocs_credit_status']) ? 1 : ($posted ? 0 : (int)$this->config->get('module_ocs_credit_status'));

		if ($rows === null) {
			$rows = (array)$this->config->get('module_ocs_credit_plans');
		}

		$data['plans'] = array();

		foreach (array_values($rows) as $row) {
			$data['plans'][] = $this->row($row);
		}

		while (count($data['plans']) < $this->slots) {
			$data['plans'][] = $this->row(array());
		}

		$texts = $posted && isset($this->request->post['module_ocs_credit_l']) ? $this->decode($this->request->post['module_ocs_credit_l']) : (array)$this->config->get('module_ocs_credit_l');

		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['l'] = array();

		foreach ($data['languages'] as $language) {
			foreach (array('disclaimer', 'terms') as $field) {
				$data['l'][$language['code']][$field] = $this->e(isset($texts[$language['code']][$field]) ? html_entity_decode((string)$texts[$language['code']][$field], ENT_QUOTES, 'UTF-8') : '');
			}
		}

		$data['version'] = '1.0.0';
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_credit', $data));
	}

	private function row($row) {
		$out = array();

		foreach (array('bank', 'months', 'rate', 'fee', 'min', 'max') as $field) {
			$value = isset($row[$field]) ? $row[$field] : '';

			$out[$field] = $this->e(is_float($value) && $value == 0 ? '' : $value);
		}

		return $out;
	}

	private function e($value) {
		return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Core request cleaning entity-encodes the input; settings keep the raw text and every output escapes it once.
	 */
	private function decode($value) {
		if (is_array($value)) {
			return array_map(array($this, 'decode'), $value);
		}

		return html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8');
	}
}
