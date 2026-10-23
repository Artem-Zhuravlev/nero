<?php
// Extension: ocs_benefits. Admin: status and four benefits (icon + title and text per language). Owner: SPEC-038 (REQ-LAY-006).

class ControllerExtensionModuleOcsBenefits extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/ocs_benefits');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('localisation/language');

		$token = 'user_token=' . $this->session->data['user_token'];
		$languages = $this->model_localisation_language->getLanguages();
		$data['success'] = '';
		$posted = $this->request->server['REQUEST_METHOD'] == 'POST';

		if ($posted) {
			if (!$this->user->hasPermission('modify', 'extension/module/ocs_benefits')) {
				$this->error['warning'] = $this->language->get('error_permission');
			} else {
				$items = array();

				foreach ((array)(isset($this->request->post['item']) ? $this->request->post['item'] : array()) as $row) {
					$icon = isset($row['icon']) ? trim($row['icon']) : '';

					if ($icon !== '' && !preg_match('/^[a-z0-9-]{1,32}$/', $icon)) {
						$this->error['icon'] = $this->language->get('error_icon');
					}

					$l = array();

					foreach ($languages as $language) {
						$l[$language['code']] = array(
							'title' => isset($row['l'][$language['code']]['title']) ? html_entity_decode((string)$row['l'][$language['code']]['title'], ENT_QUOTES, 'UTF-8') : '',
							'text'  => isset($row['l'][$language['code']]['text']) ? html_entity_decode((string)$row['l'][$language['code']]['text'], ENT_QUOTES, 'UTF-8') : ''
						);
					}

					$items[] = array('icon' => $icon, 'l' => $l);
				}

				if (!$this->error) {
					$this->model_setting_setting->editSetting('module_ocs_benefits', array(
						'module_ocs_benefits_status' => !empty($this->request->post['module_ocs_benefits_status']) ? 1 : 0,
						'module_ocs_benefits_items'  => array_slice($items, 0, 4)
					));

					$data['success'] = $this->language->get('text_success');
				}
			}
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['error_icon'] = isset($this->error['icon']) ? $this->error['icon'] : '';
		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $token . '&type=module', true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/ocs_benefits', $token, true));
		$data['action'] = $this->url->link('extension/module/ocs_benefits', $token, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=module', true);
		$data['status'] = $posted ? (int)!empty($this->request->post['module_ocs_benefits_status']) : (int)$this->config->get('module_ocs_benefits_status');
		$data['languages'] = $languages;

		$stored = $posted && isset($this->request->post['item']) ? array_values($this->request->post['item']) : array_values((array)$this->config->get('module_ocs_benefits_items'));
		$data['items'] = array();

		for ($i = 0; $i < 4; $i++) {
			$row = isset($stored[$i]) ? $stored[$i] : array();
			$item = array('icon' => htmlspecialchars(isset($row['icon']) ? (string)$row['icon'] : '', ENT_QUOTES, 'UTF-8'), 'l' => array());

			foreach ($languages as $language) {
				foreach (array('title', 'text') as $field) {
					$value = isset($row['l'][$language['code']][$field]) ? html_entity_decode((string)$row['l'][$language['code']][$field], ENT_QUOTES, 'UTF-8') : '';

					$item['l'][$language['code']][$field] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
				}
			}

			$data['items'][] = $item;
		}

		$data['version'] = '1.0.0';
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_benefits', $data));
	}
}
