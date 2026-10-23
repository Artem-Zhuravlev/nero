<?php
// Extension: ocs_home_slider. Admin: settings, slide list, slide form. Owner: SPEC-033 (REQ-HOM-001).

class ControllerExtensionModuleOcsHomeSlider extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/ocs_home_slider');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('extension/ocs_home_slider/slide');

		$token = 'user_token=' . $this->session->data['user_token'];

		$data['success'] = '';

		if (!empty($this->session->data['ocs_slider_flash'])) {
			$data['success'] = $this->session->data['ocs_slider_flash'];

			unset($this->session->data['ocs_slider_flash']);
		}

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			if (!$this->user->hasPermission('modify', 'extension/module/ocs_home_slider')) {
				$this->error['warning'] = $this->language->get('error_permission');
			} elseif (isset($this->request->post['delete_id'])) {
				$this->model_extension_ocs_home_slider_slide->delete((int)$this->request->post['delete_id']);

				$data['success'] = $this->language->get('text_deleted');
			} elseif ($this->validateSettings()) {
				$this->model_setting_setting->editSetting('module_ocs_home_slider', $this->request->post);

				$data['success'] = $this->language->get('text_success');
			}
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $token . '&type=module', true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/ocs_home_slider', $token, true));

		$data['action'] = $this->url->link('extension/module/ocs_home_slider', $token, true);
		$data['add'] = $this->url->link('extension/module/ocs_home_slider/form', $token, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=module', true);

		$defaults = array('status' => 0, 'tiles' => 0, 'autoplay' => 6000, 'width' => 1280, 'height' => 480, 'mobile_width' => 640, 'mobile_height' => 480);

		foreach ($defaults as $field => $default) {
			$key = 'module_ocs_home_slider_' . $field;

			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} elseif ($this->config->has($key)) {
				$data[$key] = $this->config->get($key);
			} else {
				$data[$key] = $default;
			}
		}

		$this->load->model('tool/image');

		$data['slides'] = array();

		foreach ($this->model_extension_ocs_home_slider_slide->getSlides() as $row) {
			$data['slides'][] = array(
				'slide_id'   => $row['slide_id'],
				'title'      => $row['title'],
				'thumb'      => $row['image'] && is_file(DIR_IMAGE . $row['image']) ? $this->model_tool_image->resize($row['image'], 80, 40) : $this->model_tool_image->resize('no_image.png', 80, 40),
				'sort_order' => $row['sort_order'],
				'period'     => ($row['date_from'] ?: '…') . ' – ' . ($row['date_to'] ?: '…'),
				'status'     => $row['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
				'edit'       => $this->url->link('extension/module/ocs_home_slider/form', $token . '&slide_id=' . (int)$row['slide_id'], true)
			);
		}

		$data['version'] = '1.0.0';

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_home_slider', $data));
	}

	public function form() {
		$this->load->language('extension/module/ocs_home_slider');

		$this->load->model('extension/ocs_home_slider/slide');
		$this->load->model('localisation/language');
		$this->load->model('tool/image');

		$token = 'user_token=' . $this->session->data['user_token'];
		$slide_id = isset($this->request->get['slide_id']) ? (int)$this->request->get['slide_id'] : 0;

		$this->document->setTitle($this->language->get($slide_id ? 'heading_edit' : 'heading_add'));

		$languages = $this->model_localisation_language->getLanguages();

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validateSlide()) {
			$post = $this->request->post;
			$descriptions = array();

			foreach ($languages as $language) {
				$d = isset($post['description'][$language['language_id']]) ? $post['description'][$language['language_id']] : array();

				$descriptions[(int)$language['language_id']] = array('title' => trim(isset($d['title']) ? $d['title'] : ''), 'text' => trim(isset($d['text']) ? $d['text'] : ''));
			}

			$this->model_extension_ocs_home_slider_slide->save($slide_id, array(
				'image'        => $post['image'],
				'image_mobile' => $post['image_mobile'],
				'link'         => trim($post['link']),
				'sort_order'   => (int)$post['sort_order'],
				'status'       => !empty($post['status']),
				'date_from'    => trim($post['date_from']),
				'date_to'      => trim($post['date_to']),
				'descriptions' => $descriptions
			));

			$this->session->data['ocs_slider_flash'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('extension/module/ocs_home_slider', $token, true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		$info = $slide_id ? $this->model_extension_ocs_home_slider_slide->getSlide($slide_id) : array();

		foreach (array('image' => '', 'image_mobile' => '', 'link' => '', 'sort_order' => 0, 'status' => 1, 'date_from' => '', 'date_to' => '') as $field => $default) {
			$data[$field] = isset($this->request->post[$field]) ? $this->request->post[$field] : (isset($info[$field]) ? $info[$field] : $default);
		}

		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);

		foreach (array('image', 'image_mobile') as $field) {
			$data['thumb_' . $field] = $data[$field] && is_file(DIR_IMAGE . $data[$field]) ? $this->model_tool_image->resize($data[$field], 100, 100) : $data['placeholder'];
		}

		$data['languages'] = $languages;
		$data['descriptions'] = array();

		foreach ($languages as $language) {
			$lid = (int)$language['language_id'];

			$data['descriptions'][$lid] = isset($this->request->post['description'][$lid]) ? $this->request->post['description'][$lid] : (isset($info['descriptions'][$lid]) ? $info['descriptions'][$lid] : array('title' => '', 'text' => ''));
		}

		$data['heading'] = $this->language->get($slide_id ? 'heading_edit' : 'heading_add');

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/ocs_home_slider', $token, true));

		$data['action'] = $this->url->link('extension/module/ocs_home_slider/form', $token . ($slide_id ? '&slide_id=' . $slide_id : ''), true);
		$data['cancel'] = $this->url->link('extension/module/ocs_home_slider', $token, true);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_home_slider_form', $data));
	}

	protected function validateSettings() {
		$p = $this->request->post;

		foreach (array('width' => array(100, 4000), 'height' => array(50, 3000), 'mobile_width' => array(100, 4000), 'mobile_height' => array(50, 3000), 'autoplay' => array(0, 30000)) as $field => $range) {
			$v = isset($p['module_ocs_home_slider_' . $field]) ? $p['module_ocs_home_slider_' . $field] : '';

			if (!ctype_digit((string)$v) || (int)$v < $range[0] || (int)$v > $range[1]) {
				$this->error['warning'] = $this->language->get('error_values');
			}
		}

		return !$this->error;
	}

	protected function validateSlide() {
		if (!$this->user->hasPermission('modify', 'extension/module/ocs_home_slider')) {
			$this->error['warning'] = $this->language->get('error_permission');

			return false;
		}

		$p = $this->request->post;

		if (empty($p['image'])) {
			$this->error['warning'] = $this->language->get('error_image');
		}

		foreach (array('date_from', 'date_to') as $field) {
			if (!empty($p[$field]) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $p[$field])) {
				$this->error['warning'] = $this->language->get('error_date');
			}
		}

		$link = htmlspecialchars_decode(isset($p['link']) ? trim($p['link']) : '', ENT_QUOTES);

		if ($link !== '' && !preg_match('#^(https?://|/(?!/))#i', $link)) {
			$this->error['warning'] = $this->language->get('error_link');
		}

		if (!isset($p['sort_order']) || !preg_match('/^-?\d{1,9}$/', (string)$p['sort_order'])) {
			$this->error['warning'] = $this->language->get('error_values');
		}

		return !$this->error;
	}
}
