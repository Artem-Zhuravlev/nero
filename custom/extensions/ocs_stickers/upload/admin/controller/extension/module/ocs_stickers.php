<?php
// Extension: ocs_stickers. Admin: settings, sticker list, add/edit form, delete. Owner: SPEC-029 (REQ-STK-001..005, 011).

class ControllerExtensionModuleOcsStickers extends Controller {
	private $error = array();

	/**
	 * Settings and the list of stickers.
	 */
	public function index() {
		$this->load->language('extension/module/ocs_stickers');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('extension/ocs_stickers/sticker');

		$token = 'user_token=' . $this->session->data['user_token'];

		$data['success'] = '';
		$data['warning'] = '';

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			if (!$this->user->hasPermission('modify', 'extension/module/ocs_stickers')) {
				$this->error['warning'] = $this->language->get('error_permission');
			} elseif (isset($this->request->post['delete_id'])) {
				$this->model_extension_ocs_stickers_sticker->delete((int)$this->request->post['delete_id']);

				$data['success'] = $this->language->get('text_deleted');
			} else {
				$limit = isset($this->request->post['module_ocs_stickers_limit']) ? (int)$this->request->post['module_ocs_stickers_limit'] : 0;

				if ($limit < 1 || $limit > 5) {
					$this->error['warning'] = $this->language->get('error_limit');
				} else {
					$this->model_setting_setting->editSetting('module_ocs_stickers', $this->request->post);

					$data['success'] = $this->language->get('text_success');
				}
			}
		}

		if (!empty($this->session->data['ocs_sticker_flash'])) {
			$data['success'] = $this->session->data['ocs_sticker_flash'];

			unset($this->session->data['ocs_sticker_flash']);
		}

		if (!empty($this->session->data['ocs_sticker_warning'])) {
			$data['warning'] = $this->session->data['ocs_sticker_warning'];

			unset($this->session->data['ocs_sticker_warning']);
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $token . '&type=module', true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/ocs_stickers', $token, true));

		$data['action'] = $this->url->link('extension/module/ocs_stickers', $token, true);
		$data['add'] = $this->url->link('extension/module/ocs_stickers/form', $token, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=module', true);

		$data['module_ocs_stickers_status'] = isset($this->request->post['module_ocs_stickers_status']) ? $this->request->post['module_ocs_stickers_status'] : $this->config->get('module_ocs_stickers_status');
		$data['module_ocs_stickers_limit'] = isset($this->request->post['module_ocs_stickers_limit']) ? $this->request->post['module_ocs_stickers_limit'] : ($this->config->get('module_ocs_stickers_limit') ?: 2);

		$data['stickers'] = array();

		foreach ($this->model_extension_ocs_stickers_sticker->getStickers() as $row) {
			$data['stickers'][] = array(
				'sticker_id' => $row['sticker_id'],
				'name'       => $row['name'],
				'fg'         => \Ocs\Stickers::safeColor($row['text_color'], '#ffffff'),
				'bg'         => \Ocs\Stickers::safeColor($row['bg_color'], '#1d1d1f'),
				'position'   => $this->language->get('text_pos_' . $row['position']),
				'priority'   => $row['priority'],
				'rule'       => $this->language->get('text_rule_' . $row['rule_type']) . ($row['rule_value'] ? ' (' . (int)$row['rule_value'] . ')' : ''),
				'assigned'   => $row['assigned'],
				'status'     => $row['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
				'edit'       => $this->url->link('extension/module/ocs_stickers/form', $token . '&sticker_id=' . (int)$row['sticker_id'], true)
			);
		}

		$data['version'] = '1.0.0';

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_stickers', $data));
	}

	/**
	 * Add / edit one sticker.
	 */
	public function form() {
		$this->load->language('extension/module/ocs_stickers');

		$this->load->model('extension/ocs_stickers/sticker');
		$this->load->model('localisation/language');

		$token = 'user_token=' . $this->session->data['user_token'];
		$sticker_id = isset($this->request->get['sticker_id']) ? (int)$this->request->get['sticker_id'] : 0;

		$this->document->setTitle($this->language->get($sticker_id ? 'heading_edit' : 'heading_add'));

		$languages = $this->model_localisation_language->getLanguages();

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate($languages)) {
			$post = $this->request->post;

			$names = array();

			foreach ($languages as $language) {
				$names[(int)$language['language_id']] = trim($post['name'][$language['language_id']]);
			}

			$fg = \Ocs\Stickers::safeColor($post['text_color'], '#ffffff');
			$bg = \Ocs\Stickers::safeColor($post['bg_color'], '#1d1d1f');

			$id = $this->model_extension_ocs_stickers_sticker->save($sticker_id, array(
				'names'           => $names,
				'text_color'      => $fg,
				'bg_color'        => $bg,
				'position'        => $post['position'],
				'priority'        => (int)$post['priority'],
				'rule_type'       => $post['rule_type'],
				'rule_value'      => (int)$post['rule_value'],
				'show_pct'        => !empty($post['show_pct']),
				'category_id'     => (int)$post['category_id'],
				'manufacturer_id' => (int)$post['manufacturer_id'],
				'status'          => !empty($post['status'])
			));

			$this->model_extension_ocs_stickers_sticker->setAssigned($id, preg_split('/[\s,;]+/', trim((string)$post['products']), -1, PREG_SPLIT_NO_EMPTY));

			$this->session->data['ocs_sticker_flash'] = $this->language->get('text_success');

			if (\Ocs\Stickers::contrast($fg, $bg) < 4.5) {
				$this->session->data['ocs_sticker_warning'] = sprintf($this->language->get('text_contrast'), number_format(\Ocs\Stickers::contrast($fg, $bg), 1));
			}

			$this->response->redirect($this->url->link('extension/module/ocs_stickers', $token, true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		$info = $sticker_id ? $this->model_extension_ocs_stickers_sticker->getSticker($sticker_id) : array();

		$defaults = array('text_color' => '#ffffff', 'bg_color' => '#0071e3', 'position' => 'tl', 'priority' => 0, 'rule_type' => 'manual', 'rule_value' => 0, 'show_pct' => 0, 'category_id' => 0, 'manufacturer_id' => 0, 'status' => 1);

		foreach ($defaults as $field => $default) {
			if (isset($this->request->post[$field])) {
				$data[$field] = $this->request->post[$field];
			} elseif (isset($info[$field])) {
				$data[$field] = $info[$field];
			} else {
				$data[$field] = $default;
			}
		}

		$data['languages'] = $languages;
		$data['names'] = array();

		foreach ($languages as $language) {
			$lid = (int)$language['language_id'];

			$data['names'][$lid] = isset($this->request->post['name'][$lid]) ? $this->request->post['name'][$lid] : (isset($info['names'][$lid]) ? $info['names'][$lid] : '');
		}

		$data['products'] = isset($this->request->post['products']) ? $this->request->post['products'] : ($sticker_id ? implode(', ', $this->model_extension_ocs_stickers_sticker->getAssigned($sticker_id)) : '');
		$data['rules'] = \Ocs\Stickers::$rules;
		$data['positions'] = \Ocs\Stickers::$positions;

		$data['heading'] = $this->language->get($sticker_id ? 'heading_edit' : 'heading_add');

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/ocs_stickers', $token, true));

		$data['action'] = $this->url->link('extension/module/ocs_stickers/form', $token . ($sticker_id ? '&sticker_id=' . $sticker_id : ''), true);
		$data['cancel'] = $this->url->link('extension/module/ocs_stickers', $token, true);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_stickers_form', $data));
	}

	protected function validate($languages) {
		if (!$this->user->hasPermission('modify', 'extension/module/ocs_stickers')) {
			$this->error['warning'] = $this->language->get('error_permission');

			return false;
		}

		$post = $this->request->post;

		foreach ($languages as $language) {
			$name = isset($post['name'][$language['language_id']]) ? trim($post['name'][$language['language_id']]) : '';

			if ($name === '' || utf8_strlen($name) > 32) {
				$this->error['warning'] = $this->language->get('error_name');
			}
		}

		if (!isset($post['rule_type']) || !in_array($post['rule_type'], \Ocs\Stickers::$rules, true) || !isset($post['position']) || !in_array($post['position'], \Ocs\Stickers::$positions, true)) {
			$this->error['warning'] = $this->language->get('error_values');
		}

		foreach (array('priority', 'rule_value', 'category_id', 'manufacturer_id') as $field) {
			if (!isset($post[$field]) || !preg_match('/^-?\d{1,9}$/', (string)$post[$field])) {
				$this->error['warning'] = $this->language->get('error_values');
			}
		}

		if (isset($post['products']) && trim($post['products']) !== '' && !preg_match('/^[\d\s,;]+$/', $post['products'])) {
			$this->error['warning'] = $this->language->get('error_products');
		}

		return !$this->error;
	}
}
