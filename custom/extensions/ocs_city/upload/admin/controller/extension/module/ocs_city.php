<?php
// Extension: ocs_city. Admin: settings, cities with delivery rules, stores. Owner: SPEC-032 (REQ-CTY-001, 010, 020).

class ControllerExtensionModuleOcsCity extends Controller {
	private $error = array();

	public function index() {
		$this->boot();

		$model = $this->model_extension_ocs_city_city;
		$data['success'] = '';

		if (!empty($this->session->data['ocs_city_flash'])) {
			$data['success'] = $this->session->data['ocs_city_flash'];

			unset($this->session->data['ocs_city_flash']);
		}

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			if (!$this->user->hasPermission('modify', 'extension/module/ocs_city')) {
				$this->error['warning'] = $this->language->get('error_permission');
			} elseif (isset($this->request->post['delete_city'])) {
				$model->deleteCity((int)$this->request->post['delete_city']);

				$data['success'] = $this->language->get('text_deleted');
			} elseif (isset($this->request->post['delete_store'])) {
				$model->deleteStore((int)$this->request->post['delete_store']);

				$data['success'] = $this->language->get('text_deleted');
			} else {
				$post = $this->request->post;
				$post['module_ocs_city_l'] = $this->decode(isset($post['module_ocs_city_l']) ? $post['module_ocs_city_l'] : array());

				if (isset($post['module_ocs_city_pickup_minutes']) && (!ctype_digit((string)$post['module_ocs_city_pickup_minutes']) || (int)$post['module_ocs_city_pickup_minutes'] > 1440)) {
					$this->error['minutes'] = $this->language->get('error_minutes');
				} else {
					$this->model_setting_setting->editSetting('module_ocs_city', $post);
					$this->cache->delete('ocs_city');

					$data['success'] = $this->language->get('text_success');
				}
			}
		}

		$token = $this->token();

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['error_minutes'] = isset($this->error['minutes']) ? $this->error['minutes'] : '';
		$data['breadcrumbs'] = $this->crumbs();
		$data['action'] = $this->url->link('extension/module/ocs_city', $token, true);
		$data['add_city'] = $this->url->link('extension/module/ocs_city/city', $token, true);
		$data['add_store'] = $this->url->link('extension/module/ocs_city/store', $token, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=module', true);

		foreach (array('status' => 0, 'tracking' => 1, 'pickup_minutes' => 0) as $field => $default) {
			$key = 'module_ocs_city_' . $field;

			$data[$key] = isset($this->request->post[$key]) ? $this->request->post[$key] : ($this->config->has($key) ? $this->config->get($key) : $default);
		}

		$texts = isset($this->request->post['module_ocs_city_l']) ? $this->decode($this->request->post['module_ocs_city_l']) : (array)$this->config->get('module_ocs_city_l');

		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['l'] = array();

		foreach ($data['languages'] as $language) {
			foreach (array('payment', 'warranty', 'returns') as $field) {
				$data['l'][$language['code']][$field] = $this->e(isset($texts[$language['code']][$field]) ? html_entity_decode((string)$texts[$language['code']][$field], ENT_QUOTES, 'UTF-8') : '');
			}
		}

		$data['cities'] = array();

		foreach ($model->getCities() as $row) {
			$data['cities'][] = array('city_id' => $row['city_id'], 'name' => $this->e($row['name']), 'is_default' => $row['is_default'], 'status' => $row['status'], 'edit' => $this->url->link('extension/module/ocs_city/city', $token . '&city_id=' . (int)$row['city_id'], true));
		}

		$data['stores'] = array();

		foreach ($model->getStores() as $row) {
			$data['stores'][] = array('store_id' => $row['store_id'], 'name' => $this->e($row['name']), 'city' => $this->e($row['city']), 'status' => $row['status'], 'edit' => $this->url->link('extension/module/ocs_city/store', $token . '&store_id=' . (int)$row['store_id'], true));
		}

		$data['version'] = '1.0.0';
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_city', $data));
	}

	public function city() {
		$this->boot();

		$model = $this->model_extension_ocs_city_city;
		$city_id = isset($this->request->get['city_id']) ? (int)$this->request->get['city_id'] : 0;
		$langs = array_map('intval', array_column($this->model_localisation_language->getLanguages(), 'language_id'));
		$errors = array();
		$info = $city_id ? $model->getCity($city_id) : array();

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			if (!$this->user->hasPermission('modify', 'extension/module/ocs_city')) {
				$this->error['warning'] = $this->language->get('error_permission');
			} else {
				$result = \Ocs\City::cleanCity($this->decode($this->request->post), $langs);
				$errors = $result['errors'];

				if (!$errors) {
					$model->saveCity($city_id, $result['data']);

					$this->session->data['ocs_city_flash'] = $this->language->get('text_success');

					$this->response->redirect($this->url->link('extension/module/ocs_city', $this->token(), true));
				}

				$info = $this->fromPost($result['data']);
			}
		}

		$token = $this->token();

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['errors'] = array();

		foreach ($errors as $field => $code) {
			$data['errors'][] = $this->language->get('error_' . $code);
		}

		$data['breadcrumbs'] = $this->crumbs();
		$data['action'] = $this->url->link('extension/module/ocs_city/city', $token . ($city_id ? '&city_id=' . $city_id : ''), true);
		$data['cancel'] = $this->url->link('extension/module/ocs_city', $token, true);
		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['methods'] = \Ocs\City::METHODS;

		$data['sort_order'] = isset($info['sort_order']) ? (int)$info['sort_order'] : 0;
		$data['is_default'] = !empty($info['is_default']);
		$data['status'] = isset($info['status']) ? (int)$info['status'] : 1;
		$data['names'] = array();
		$data['rules'] = array();

		foreach ($data['languages'] as $language) {
			$data['names'][$language['language_id']] = $this->e(isset($info['names'][$language['language_id']]) ? $info['names'][$language['language_id']] : '');
		}

		foreach (\Ocs\City::METHODS as $method) {
			$r = isset($info['rules'][$method]) ? $info['rules'][$method] : array();

			$rule = array(
				'method'    => $method,
				'label'     => $this->language->get('method_' . $method),
				'status'    => !empty($r),
				'price'     => isset($r['price']) ? (float)$r['price'] : 0,
				'free_from' => isset($r['free_from']) ? (float)$r['free_from'] : 0,
				'days_min'  => isset($r['days_min']) ? (int)$r['days_min'] : 0,
				'days_max'  => isset($r['days_max']) ? (int)$r['days_max'] : 0,
				'cutoff'    => isset($r['cutoff']) ? $this->e($r['cutoff']) : '',
				'notes'     => array()
			);

			foreach ($data['languages'] as $language) {
				$rule['notes'][$language['language_id']] = $this->e(isset($r['notes'][$language['language_id']]) ? $r['notes'][$language['language_id']] : '');
			}

			$data['rules'][] = $rule;
		}

		$this->form('extension/module/ocs_city_city', $data);
	}

	public function store() {
		$this->boot();

		$model = $this->model_extension_ocs_city_city;
		$store_id = isset($this->request->get['store_id']) ? (int)$this->request->get['store_id'] : 0;
		$langs = array_map('intval', array_column($this->model_localisation_language->getLanguages(), 'language_id'));
		$city_ids = array_map('intval', array_column($model->getCities(), 'city_id'));
		$errors = array();
		$info = $store_id ? $model->getStore($store_id) : array();

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			if (!$this->user->hasPermission('modify', 'extension/module/ocs_city')) {
				$this->error['warning'] = $this->language->get('error_permission');
			} else {
				$result = \Ocs\City::cleanStore($this->decode($this->request->post), $langs, $city_ids);
				$errors = $result['errors'];

				if (!$errors) {
					$model->saveStore($store_id, $result['data']);

					$this->session->data['ocs_city_flash'] = $this->language->get('text_success');

					$this->response->redirect($this->url->link('extension/module/ocs_city', $this->token(), true));
				}

				$info = $result['data'];
			}
		}

		$token = $this->token();

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['errors'] = array();

		foreach ($errors as $field => $code) {
			$data['errors'][] = $this->language->get('error_' . $code);
		}

		$data['breadcrumbs'] = $this->crumbs();
		$data['action'] = $this->url->link('extension/module/ocs_city/store', $token . ($store_id ? '&store_id=' . $store_id : ''), true);
		$data['cancel'] = $this->url->link('extension/module/ocs_city', $token, true);
		$data['languages'] = $this->model_localisation_language->getLanguages();

		$data['city_id'] = isset($info['city_id']) ? (int)$info['city_id'] : 0;
		$data['phone'] = $this->e(isset($info['phone']) ? $info['phone'] : '');
		$data['map_link'] = $this->e(isset($info['map_link']) ? $info['map_link'] : '');
		$data['latitude'] = $this->e(isset($info['latitude']) ? $info['latitude'] : '');
		$data['longitude'] = $this->e(isset($info['longitude']) ? $info['longitude'] : '');
		$data['sort_order'] = isset($info['sort_order']) ? (int)$info['sort_order'] : 0;
		$data['status'] = isset($info['status']) ? (int)$info['status'] : 1;
		$data['desc'] = array();

		foreach ($data['languages'] as $language) {
			$row = isset($info['desc'][$language['language_id']]) ? $info['desc'][$language['language_id']] : array();

			$data['desc'][$language['language_id']] = array('name' => $this->e(isset($row['name']) ? $row['name'] : ''), 'address' => $this->e(isset($row['address']) ? $row['address'] : ''), 'hours' => $this->e(isset($row['hours']) ? $row['hours'] : ''));
		}

		$data['cities'] = array();

		foreach ($model->getCities() as $row) {
			$data['cities'][] = array('city_id' => (int)$row['city_id'], 'name' => $this->e($row['name']));
		}

		$this->form('extension/module/ocs_city_store', $data);
	}

	private function boot() {
		$this->load->language('extension/module/ocs_city');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('localisation/language');
		$this->load->model('extension/ocs_city/city');
	}

	private function form($template, $data) {
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view($template, $data));
	}

	private function token() {
		return 'user_token=' . $this->session->data['user_token'];
	}

	private function crumbs() {
		$token = $this->token();

		return array(
			array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true)),
			array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $token . '&type=module', true)),
			array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/ocs_city', $token, true))
		);
	}

	private function e($value) {
		return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Core request cleaning entity-encodes the input; the tables keep the raw text and every output escapes it once.
	 */
	private function decode($value) {
		if (is_array($value)) {
			return array_map(array($this, 'decode'), $value);
		}

		return html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Cleaned form data back into the shape of getCity() so a failed save keeps what was typed.
	 */
	private function fromPost($d) {
		$rules = array();

		foreach ($d['rules'] as $method => $r) {
			$rules[$method] = $r;
		}

		return array('sort_order' => $d['sort_order'], 'is_default' => $d['is_default'], 'status' => $d['status'], 'names' => $d['names'], 'rules' => $rules);
	}
}
