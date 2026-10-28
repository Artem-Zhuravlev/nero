<?php
// Extension: ocs_blog. Admin: list and form of blog authors. Owner: SPEC-084 (REQ-BLG-001, REQ-BLG-002).

class ControllerExtensionOcsBlogAuthor extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/ocs_blog/author');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/ocs_blog/author');

		$this->getList();
	}

	public function add() {
		$this->load->language('extension/ocs_blog/author');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/ocs_blog/author');

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validateForm()) {
			$this->model_extension_ocs_blog_author->addAuthor($this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('extension/ocs_blog/author', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('extension/ocs_blog/author');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/ocs_blog/author');

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validateForm()) {
			$this->model_extension_ocs_blog_author->editAuthor((int)$this->request->get['author_id'], $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('extension/ocs_blog/author', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getForm();
	}

	public function delete() {
		$this->load->language('extension/ocs_blog/author');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/ocs_blog/author');

		if (isset($this->request->post['selected']) && is_array($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $author_id) {
				$this->model_extension_ocs_blog_author->deleteAuthor((int)$author_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');
		}

		$this->response->redirect($this->url->link('extension/ocs_blog/author', 'user_token=' . $this->session->data['user_token'], true));
	}

	private function crumbs() {
		$token = 'user_token=' . $this->session->data['user_token'];

		return array(
			array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true)),
			array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/ocs_blog/author', $token, true))
		);
	}

	private function getList() {
		$token = 'user_token=' . $this->session->data['user_token'];

		$data['breadcrumbs'] = $this->crumbs();
		$data['add'] = $this->url->link('extension/ocs_blog/author/add', $token, true);
		$data['delete'] = $this->url->link('extension/ocs_blog/author/delete', $token, true);
		$data['authors'] = array();

		$this->load->model('tool/image');

		foreach ($this->model_extension_ocs_blog_author->getAuthors() as $row) {
			$data['authors'][] = array(
				'author_id'  => $row['author_id'],
				'name'       => $row['name'],
				'position'   => $row['position'],
				'thumb'      => $this->model_tool_image->resize($row['image'] && is_file(DIR_IMAGE . $row['image']) ? $row['image'] : 'no_image.png', 40, 40),
				'sort_order' => $row['sort_order'],
				'status'     => $row['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
				'edit'       => $this->url->link('extension/ocs_blog/author/edit', $token . '&author_id=' . $row['author_id'], true)
			);
		}

		foreach (array('heading_title', 'text_list', 'text_no_results', 'text_confirm', 'column_image', 'column_name', 'column_position', 'column_sort_order', 'column_status', 'column_action', 'button_add', 'button_edit', 'button_delete') as $key) {
			$data[$key] = $this->language->get($key);
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/ocs_blog/author_list', $data));
	}

	private function getForm() {
		$token = 'user_token=' . $this->session->data['user_token'];
		$author_id = isset($this->request->get['author_id']) ? (int)$this->request->get['author_id'] : 0;

		$data['breadcrumbs'] = $this->crumbs();
		$data['action'] = $this->url->link('extension/ocs_blog/author/' . ($author_id ? 'edit&author_id=' . $author_id : 'add'), $token, true);
		$data['cancel'] = $this->url->link('extension/ocs_blog/author', $token, true);

		foreach (array('heading_title', 'text_form', 'text_enabled', 'text_disabled', 'entry_name', 'entry_position', 'entry_description', 'entry_image', 'entry_status', 'entry_sort_order', 'entry_social', 'help_social', 'tab_general', 'tab_social', 'button_save', 'button_cancel') as $key) {
			$data[$key] = $this->language->get($key);
		}

		$data['social_keys'] = array();

		foreach (\Ocs\Blog::SOCIAL as $key) {
			$data['social_keys'][$key] = $this->language->get('entry_social_' . $key);
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['error_name'] = isset($this->error['name']) ? $this->error['name'] : array();
		$data['error_social'] = isset($this->error['social']) ? $this->error['social'] : '';

		$info = $author_id ? $this->model_extension_ocs_blog_author->getAuthor($author_id) : array();
		$descriptions = $author_id ? $this->model_extension_ocs_blog_author->getDescriptions($author_id) : array();

		$this->load->model('localisation/language');
		$this->load->model('tool/image');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		$data['description'] = isset($this->request->post['description']) ? $this->request->post['description'] : $descriptions;
		$data['image'] = isset($this->request->post['image']) ? $this->request->post['image'] : (isset($info['image']) ? $info['image'] : '');
		$data['social'] = isset($this->request->post['social']) ? $this->request->post['social'] : (isset($info['social']) ? $info['social'] : array());
		$data['sort_order'] = isset($this->request->post['sort_order']) ? $this->request->post['sort_order'] : (isset($info['sort_order']) ? $info['sort_order'] : 0);
		$data['status'] = isset($this->request->post['status']) ? $this->request->post['status'] : (isset($info['status']) ? $info['status'] : 1);

		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);
		$data['thumb'] = $this->model_tool_image->resize($data['image'] && is_file(DIR_IMAGE . $data['image']) ? $data['image'] : 'no_image.png', 100, 100);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/ocs_blog/author_form', $data));
	}

	private function validateForm() {
		if (!$this->user->hasPermission('modify', 'extension/ocs_blog/author')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		foreach ((array)(isset($this->request->post['description']) ? $this->request->post['description'] : array()) as $language_id => $value) {
			$name = isset($value['name']) ? trim($value['name']) : '';

			if (utf8_strlen($name) < 2 || utf8_strlen($name) > 128) {
				$this->error['name'][$language_id] = $this->language->get('error_name');
			}
		}

		if (!empty($this->request->post['social']) && is_array($this->request->post['social'])) {
			$clean = \Ocs\Blog::cleanSocial($this->request->post['social']);

			foreach ($this->request->post['social'] as $key => $url) {
				if (trim((string)$url) !== '' && !isset($clean[$key])) {
					$this->error['social'] = $this->language->get('error_social');
				}
			}
		}

		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('error_warning');
		}

		return !$this->error;
	}

	private function validateDelete() {
		if (!$this->user->hasPermission('modify', 'extension/ocs_blog/author')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
