<?php
// Extension: ocs_product_extras. Admin: settings and question moderation. Owner: SPEC-035 (REQ-PRD-010, REQ-PRD-041).

class ControllerExtensionModuleOcsProductExtras extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/ocs_product_extras');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('extension/ocs_product_extras/extras');

		$model = $this->model_extension_ocs_product_extras_extras;
		$token = 'user_token=' . $this->session->data['user_token'];
		$data['success'] = '';

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			if (!$this->user->hasPermission('modify', 'extension/module/ocs_product_extras')) {
				$this->error['warning'] = $this->language->get('error_permission');
			} elseif (isset($this->request->post['question_id'])) {
				$id = (int)$this->request->post['question_id'];

				if (isset($this->request->post['delete'])) {
					$model->deleteQuestion($id);

					$data['success'] = $this->language->get('text_deleted');
				} else {
					// core request cleaning entity-encodes the input; the table keeps the raw text and every output escapes it once
					$answer = trim(html_entity_decode((string)(isset($this->request->post['answer']) ? $this->request->post['answer'] : ''), ENT_QUOTES, 'UTF-8'));

					$model->answer($id, utf8_substr($answer, 0, 2000), !empty($this->request->post['publish']));

					$data['success'] = $this->language->get('text_saved');
				}
			} elseif ($this->validateSettings()) {
				$this->model_setting_setting->editSetting('module_ocs_product_extras', $this->request->post);

				$data['success'] = $this->language->get('text_success');
			}
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['error_limit'] = isset($this->error['limit']) ? $this->error['limit'] : '';
		$data['error_attrs'] = isset($this->error['attrs']) ? $this->error['attrs'] : '';

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $token . '&type=module', true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/ocs_product_extras', $token, true));

		$data['action'] = $this->url->link('extension/module/ocs_product_extras', $token, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=module', true);

		$defaults = array('status' => 0, 'key_attrs' => '', 'questions' => 1, 'accessories' => 1, 'similar' => 1, 'similar_limit' => 10);

		foreach ($defaults as $field => $default) {
			$key = 'module_ocs_product_extras_' . $field;

			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} elseif ($this->config->has($key)) {
				$data[$key] = $this->config->get($key);
			} else {
				$data[$key] = $default;
			}
		}

		$page = isset($this->request->get['page']) ? max(1, (int)$this->request->get['page']) : 1;
		$limit = 20;

		$data['questions'] = array();

		foreach ($model->getQuestions(($page - 1) * $limit, $limit) as $row) {
			$data['questions'][] = array(
				'question_id' => $row['question_id'],
				// visitor input is printed unescaped by the admin Twig (autoescape is off): escape once here
				'product'     => htmlspecialchars((string)$row['product'], ENT_QUOTES, 'UTF-8'),
				'product_url' => HTTP_CATALOG . 'index.php?route=product/product&product_id=' . (int)$row['product_id'],
				'name'        => htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'),
				'email'       => htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'),
				'text'        => nl2br(htmlspecialchars($row['text'], ENT_QUOTES, 'UTF-8')),
				'answer'      => htmlspecialchars($row['answer'], ENT_QUOTES, 'UTF-8'),
				'status'      => $row['status'],
				'date_added'  => $row['date_added']
			);
		}

		$total = $model->getTotalQuestions();

		$pagination = new Pagination();
		$pagination->total = $total;
		$pagination->page = $page;
		$pagination->limit = $limit;
		$pagination->url = $this->url->link('extension/module/ocs_product_extras', $token . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['total'] = $total;
		$data['pending'] = $model->getPending();
		$data['version'] = '1.0.0';

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_product_extras', $data));
	}

	protected function validateSettings() {
		$post = $this->request->post;

		if (!isset($post['module_ocs_product_extras_similar_limit']) || !ctype_digit((string)$post['module_ocs_product_extras_similar_limit']) || (int)$post['module_ocs_product_extras_similar_limit'] < 1 || (int)$post['module_ocs_product_extras_similar_limit'] > 20) {
			$this->error['limit'] = $this->language->get('error_limit');
		}

		$attrs = isset($post['module_ocs_product_extras_key_attrs']) ? trim($post['module_ocs_product_extras_key_attrs']) : '';

		if ($attrs !== '' && !preg_match('/^\d+(\s*,\s*\d+){0,3}$/', $attrs)) {
			$this->error['attrs'] = $this->language->get('error_attrs');
		}

		return !$this->error;
	}
}
