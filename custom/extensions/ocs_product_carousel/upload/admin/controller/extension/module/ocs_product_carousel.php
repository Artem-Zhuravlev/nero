<?php
// Extension: ocs_product_carousel. Admin: one form per instance (core module table). Owner: SPEC-033 (REQ-HOM-020).

class ControllerExtensionModuleOcsProductCarousel extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/ocs_product_carousel');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/module');
		$this->load->model('localisation/language');

		$token = 'user_token=' . $this->session->data['user_token'];

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			if (!isset($this->request->get['module_id'])) {
				$this->model_setting_module->addModule('ocs_product_carousel', $this->request->post);
			} else {
				$this->model_setting_module->editModule($this->request->get['module_id'], $this->request->post);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', $token . '&type=module', true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['error_name'] = isset($this->error['name']) ? $this->error['name'] : '';
		$data['error_ids'] = isset($this->error['ids']) ? $this->error['ids'] : '';
		$data['error_limit'] = isset($this->error['limit']) ? $this->error['limit'] : '';

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $token . '&type=module', true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/ocs_product_carousel', $token . (isset($this->request->get['module_id']) ? '&module_id=' . (int)$this->request->get['module_id'] : ''), true));

		$data['action'] = $this->url->link('extension/module/ocs_product_carousel', $token . (isset($this->request->get['module_id']) ? '&module_id=' . (int)$this->request->get['module_id'] : ''), true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=module', true);

		$info = array();

		if (isset($this->request->get['module_id']) && $this->request->server['REQUEST_METHOD'] != 'POST') {
			$info = $this->model_setting_module->getModule($this->request->get['module_id']);
		}

		$fields = array('name' => '', 'status' => 1, 'mode' => 'newest', 'category_id' => 0, 'ids' => '', 'limit' => 10, 'show_all' => 1);

		foreach ($fields as $field => $default) {
			if (isset($this->request->post[$field])) {
				$data[$field] = $this->request->post[$field];
			} elseif (isset($info[$field])) {
				$data[$field] = $info[$field];
			} else {
				$data[$field] = $default;
			}
		}

		$data['title'] = isset($this->request->post['title']) ? $this->request->post['title'] : (isset($info['title']) ? $info['title'] : array());
		$data['languages'] = $this->model_localisation_language->getLanguages();

		$this->load->model('catalog/category');

		$data['categories'] = array();

		foreach ($this->model_catalog_category->getCategories(array('sort' => 'name', 'order' => 'ASC')) as $category) {
			$data['categories'][] = array('category_id' => $category['category_id'], 'name' => $category['name']);
		}

		$data['modes'] = array('newest', 'bestsellers', 'discounted', 'manual');

		foreach ($data['modes'] as $mode) {
			$data['text_mode_' . $mode] = $this->language->get('text_mode_' . $mode);
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_product_carousel', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/ocs_product_carousel')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$post = $this->request->post;

		if (!isset($post['name']) || utf8_strlen($post['name']) < 3 || utf8_strlen($post['name']) > 64) {
			$this->error['name'] = $this->language->get('error_name');
		}

		if (!isset($post['limit']) || !ctype_digit((string)$post['limit']) || (int)$post['limit'] < 1 || (int)$post['limit'] > 30) {
			$this->error['limit'] = $this->language->get('error_limit');
		}

		if (isset($post['mode']) && $post['mode'] == 'manual' && !preg_match('/^\d+(\s*,\s*\d+)*$/', isset($post['ids']) ? trim($post['ids']) : '')) {
			$this->error['ids'] = $this->language->get('error_ids');
		}

		if (!isset($post['mode']) || !in_array($post['mode'], array('newest', 'bestsellers', 'discounted', 'manual'), true)) {
			$this->error['warning'] = $this->language->get('error_mode');
		}

		return !$this->error;
	}
}
