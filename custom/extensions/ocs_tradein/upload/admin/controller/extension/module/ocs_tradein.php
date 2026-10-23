<?php
// Extension: ocs_tradein. Admin: settings, leads, photo download. Owner: SPEC-036 (REQ-CRD-010, 020).

class ControllerExtensionModuleOcsTradein extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/ocs_tradein');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('localisation/language');
		$this->load->model('extension/ocs_tradein/lead');

		$model = $this->model_extension_ocs_tradein_lead;
		$token = 'user_token=' . $this->session->data['user_token'];
		$data['success'] = '';

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			if (!$this->user->hasPermission('modify', 'extension/module/ocs_tradein')) {
				$this->error['warning'] = $this->language->get('error_permission');
			} elseif (isset($this->request->post['delete_lead'])) {
				$lead = $model->getLead((int)$this->request->post['delete_lead']);

				if ($lead) {
					foreach (\Ocs\Tradein::parseNames($lead['photos']) as $name) {
						@unlink($this->dir() . '/' . $name);
					}

					$model->delete($lead['lead_id']);
				}

				$data['success'] = $this->language->get('text_deleted');
			} elseif (isset($this->request->post['done_lead'])) {
				$model->setStatus((int)$this->request->post['done_lead'], !empty($this->request->post['done_value']));

				$data['success'] = $this->language->get('text_saved');
			} else {
				$post = $this->decode($this->request->post);
				$categories = isset($post['module_ocs_tradein_categories']) ? trim($post['module_ocs_tradein_categories']) : '';
				$email = isset($post['module_ocs_tradein_email']) ? trim($post['module_ocs_tradein_email']) : '';

				if ($categories !== '' && !preg_match('/^\d+(\s*,\s*\d+)*$/', $categories)) {
					$this->error['categories'] = $this->language->get('error_categories');
				}

				if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
					$this->error['email'] = $this->language->get('error_email');
				}

				if (!$this->error) {
					$this->model_setting_setting->editSetting('module_ocs_tradein', array(
						'module_ocs_tradein_status'     => !empty($post['module_ocs_tradein_status']) ? 1 : 0,
						'module_ocs_tradein_categories' => preg_replace('/\s+/', '', $categories),
						'module_ocs_tradein_email'      => $email,
						'module_ocs_tradein_l'          => isset($post['module_ocs_tradein_l']) && is_array($post['module_ocs_tradein_l']) ? $post['module_ocs_tradein_l'] : array()
					));

					$data['success'] = $this->language->get('text_success');
				}
			}
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['error_categories'] = isset($this->error['categories']) ? $this->error['categories'] : '';
		$data['error_email'] = isset($this->error['email']) ? $this->error['email'] : '';

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $token . '&type=module', true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/ocs_tradein', $token, true));

		$data['action'] = $this->url->link('extension/module/ocs_tradein', $token, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=module', true);

		$posted = $this->request->server['REQUEST_METHOD'] == 'POST' && isset($this->request->post['module_ocs_tradein_l']);

		$data['status'] = $posted ? (int)!empty($this->request->post['module_ocs_tradein_status']) : (int)$this->config->get('module_ocs_tradein_status');
		$data['categories'] = $this->e($posted ? $this->decode($this->request->post['module_ocs_tradein_categories']) : $this->config->get('module_ocs_tradein_categories'));
		$data['email'] = $this->e($posted ? $this->decode($this->request->post['module_ocs_tradein_email']) : $this->config->get('module_ocs_tradein_email'));

		$texts = $posted ? $this->decode($this->request->post['module_ocs_tradein_l']) : (array)$this->config->get('module_ocs_tradein_l');

		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['l'] = array();

		foreach ($data['languages'] as $language) {
			foreach (array('title', 'text', 'button') as $field) {
				$data['l'][$language['code']][$field] = $this->e(isset($texts[$language['code']][$field]) ? html_entity_decode((string)$texts[$language['code']][$field], ENT_QUOTES, 'UTF-8') : '');
			}
		}

		$page = isset($this->request->get['page']) ? max(1, (int)$this->request->get['page']) : 1;
		$limit = 20;

		$data['leads'] = array();

		foreach ($model->getLeads(($page - 1) * $limit, $limit) as $row) {
			$photos = array();

			foreach (\Ocs\Tradein::parseNames($row['photos']) as $name) {
				$photos[] = array('name' => $name, 'href' => $this->url->link('extension/module/ocs_tradein/photo', $token . '&file=' . $name, true));
			}

			$data['leads'][] = array(
				'lead_id'    => $row['lead_id'],
				'name'       => $this->e($row['name']),
				'phone'      => $this->e($row['phone']),
				'email'      => $this->e($row['email']),
				'device'     => nl2br($this->e($row['device'])),
				'product'    => $row['product_id'] ? HTTP_CATALOG . 'index.php?route=product/product&product_id=' . (int)$row['product_id'] : '',
				'photos'     => $photos,
				'status'     => $row['status'],
				'date_added' => $row['date_added']
			);
		}

		$total = $model->getTotal();

		$pagination = new Pagination();
		$pagination->total = $total;
		$pagination->page = $page;
		$pagination->limit = $limit;
		$pagination->url = $this->url->link('extension/module/ocs_tradein', $token . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['total'] = $total;
		$data['version'] = '1.0.0';

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_tradein', $data));
	}

	/**
	 * GET file=<stored name>: an image of a lead, only for users who may access the module.
	 */
	public function photo() {
		$name = isset($this->request->get['file']) ? (string)$this->request->get['file'] : '';

		if (!$this->user->hasPermission('access', 'extension/module/ocs_tradein') || !\Ocs\Tradein::validStoredName($name)) {
			$this->response->addHeader('HTTP/1.1 404 Not Found');
			$this->response->setOutput('');

			return;
		}

		$this->load->model('extension/ocs_tradein/lead');

		$path = $this->dir() . '/' . $name;

		if (!$this->model_extension_ocs_tradein_lead->photoExists($name) || !is_file($path)) {
			$this->response->addHeader('HTTP/1.1 404 Not Found');
			$this->response->setOutput('');

			return;
		}

		$types = array('jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif');

		$this->response->addHeader('Content-Type: ' . $types[pathinfo($name, PATHINFO_EXTENSION)]);
		$this->response->addHeader('X-Content-Type-Options: nosniff');
		$this->response->addHeader('Content-Disposition: inline; filename="' . $name . '"');
		$this->response->setOutput(file_get_contents($path));
	}

	private function dir() {
		return rtrim(DIR_STORAGE, '/\\') . '/ocs_tradein';
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
