<?php
// Extension: ocs_mega_menu. Admin settings and per-category extras (icon, popular, banner). Owner: SPEC-027 (REQ-MNU-001..004).

class ControllerExtensionModuleOcsMegaMenu extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/ocs_mega_menu');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('extension/ocs_menu/menu');

		$token = 'user_token=' . $this->session->data['user_token'];

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$post = $this->request->post;
			$extras = isset($post['ocs_extras']) ? $post['ocs_extras'] : array();

			unset($post['ocs_extras']);

			$this->model_setting_setting->editSetting('module_ocs_mega_menu', $post);
			$this->model_extension_ocs_menu_menu->saveExtras($extras);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', $token . '&type=module', true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

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
			'href' => $this->url->link('extension/module/ocs_mega_menu', $token, true)
		);

		$data['action'] = $this->url->link('extension/module/ocs_mega_menu', $token, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=module', true);

		$defaults = array('status' => 0, 'levels' => 3, 'columns' => 3, 'behaviour' => 'hover', 'brands' => 0, 'brands_limit' => 8);

		foreach ($defaults as $field => $default) {
			$key = 'module_ocs_mega_menu_' . $field;

			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} elseif ($this->config->has($key)) {
				$data[$key] = $this->config->get($key);
			} else {
				$data[$key] = $default;
			}
		}

		$this->load->model('catalog/category');

		$extras = $this->model_extension_ocs_menu_menu->getExtras();

		if (isset($this->request->post['ocs_extras'])) {
			$extras = $this->request->post['ocs_extras'];
		}

		$data['categories'] = array();

		foreach ($this->model_catalog_category->getCategories(array('sort' => 'name', 'start' => 0, 'limit' => 300)) as $category) {
			$id = (int)$category['category_id'];

			$data['categories'][] = array(
				'category_id'  => $id,
				'name'         => $category['name'],
				'icon'         => isset($extras[$id]['icon']) ? $extras[$id]['icon'] : '',
				'popular'      => !empty($extras[$id]['popular']),
				'banner_image' => isset($extras[$id]['banner_image']) ? $extras[$id]['banner_image'] : '',
				'banner_url'   => isset($extras[$id]['banner_url']) ? $extras[$id]['banner_url'] : ''
			);
		}

		$data['version'] = '1.0.0';

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_mega_menu', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/ocs_mega_menu')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$levels = isset($this->request->post['module_ocs_mega_menu_levels']) ? (int)$this->request->post['module_ocs_mega_menu_levels'] : 0;
		$columns = isset($this->request->post['module_ocs_mega_menu_columns']) ? (int)$this->request->post['module_ocs_mega_menu_columns'] : 0;

		$brands_limit = isset($this->request->post['module_ocs_mega_menu_brands_limit']) ? (int)$this->request->post['module_ocs_mega_menu_brands_limit'] : 0;

		if ($brands_limit < 1 || $brands_limit > 20 || $levels < 1 || $levels > 3 || $columns < 1 || $columns > 5 || !in_array(isset($this->request->post['module_ocs_mega_menu_behaviour']) ? $this->request->post['module_ocs_mega_menu_behaviour'] : '', array('hover', 'click'), true)) {
			$this->error['warning'] = $this->language->get('error_values');
		}

		return !$this->error;
	}
}
