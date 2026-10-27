<?php
// Extension: ocs_super_search. Admin settings, synonyms and index rebuild. Owner: SPEC-079 (REQ-SSR-007, 009, 022).

class ControllerExtensionModuleOcsSuperSearch extends Controller {
	const BATCH = 200;

	private $error = array();

	private $defaults = array('status' => 0, 'layout' => 1, 'translit' => 1, 'typo' => 1, 'min_length' => 2, 'cache_ttl' => 300, 'synonyms' => '');

	public function index() {
		$this->load->language('extension/module/ocs_super_search');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		$token = 'user_token=' . $this->session->data['user_token'];

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$post = $this->request->post;

			// editSetting replaces every key of the code: keep the index bookkeeping
			foreach (array('generation', 'built_at') as $field) {
				$post['module_ocs_super_search_' . $field] = (string)$this->config->get('module_ocs_super_search_' . $field);
			}

			$this->model_setting_setting->editSetting('module_ocs_super_search', $post);

			$parsed = \Ocs\SuperSearch::parseSynonyms(isset($post['module_ocs_super_search_synonyms']) ? $post['module_ocs_super_search_synonyms'] : '');

			$this->session->data['success'] = $this->language->get('text_success');

			if ($parsed['errors']) {
				$this->session->data['success'] .= ' ' . sprintf($this->language->get('text_ignored'), htmlspecialchars(implode(', ', $parsed['errors']), ENT_QUOTES, 'UTF-8'));
			}

			// synonyms apply at query time: only the result cache has to go
			$index = new \Ocs\SuperSearchIndex($this->db, DB_PREFIX, null);
			$index->touch();

			$this->response->redirect($this->url->link('extension/module/ocs_super_search', $token, true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['success'] = '';

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		}

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $token . '&type=module', true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/ocs_super_search', $token, true));

		$data['action'] = $this->url->link('extension/module/ocs_super_search', $token, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=module', true);
		$data['rebuild'] = str_replace('&amp;', '&', $this->url->link('extension/module/ocs_super_search/rebuild', $token, true));

		foreach ($this->defaults as $field => $default) {
			$key = 'module_ocs_super_search_' . $field;

			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} elseif ($this->config->has($key)) {
				$data[$key] = $this->config->get($key);
			} else {
				$data[$key] = $default;
			}
		}

		$built = (string)$this->config->get('module_ocs_super_search_built_at');

		$data['built_at'] = $built;
		$data['tokens'] = 0;

		if ($built !== '') {
			try {
				$data['tokens'] = (new \Ocs\SuperSearchIndex($this->db, DB_PREFIX, null))->countTokens();
			} catch (\Exception $e) {
				$data['tokens'] = 0;
			}
		}

		$data['version'] = '1.0.0';

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_super_search', $data));
	}

	/**
	 * GET after=<last product_id> (0 starts: the index is emptied). Answers JSON {done, after, processed, total}.
	 */
	public function rebuild() {
		$this->load->language('extension/module/ocs_super_search');

		$json = array();

		if (!$this->user->hasPermission('modify', 'extension/module/ocs_super_search')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			try {
				$index = new \Ocs\SuperSearchIndex($this->db, DB_PREFIX, null);

				$after = isset($this->request->get['after']) ? max(0, (int)$this->request->get['after']) : 0;

				if ($after === 0) {
					$index->clear();
					$index->setSetting('module_ocs_super_search_built_at', '');
				}

				$ids = $index->nextIds($after, self::BATCH);

				$index->reindex($ids);

				$json['processed'] = count($ids);
				$json['total'] = $index->countProducts();
				$json['after'] = $ids ? max($ids) : $after;
				$json['done'] = count($ids) < self::BATCH;

				if ($json['done']) {
					$built_at = date('Y-m-d H:i:s');

					$index->setSetting('module_ocs_super_search_built_at', $built_at);

					$json['built_at'] = $built_at;
					$json['tokens'] = $index->countTokens();
				}

				$index->touch();
			} catch (\Exception $e) {
				$this->log->write('[ocs_super_search] ' . $e->getMessage());

				$json['error'] = $this->language->get('error_rebuild');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/ocs_super_search')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$min = isset($this->request->post['module_ocs_super_search_min_length']) ? (int)$this->request->post['module_ocs_super_search_min_length'] : 0;
		$ttl = isset($this->request->post['module_ocs_super_search_cache_ttl']) ? (int)$this->request->post['module_ocs_super_search_cache_ttl'] : -1;

		if ($min < 1 || $min > 5 || $ttl < 0 || $ttl > 86400) {
			$this->error['warning'] = $this->language->get('error_values');
		}

		return !$this->error;
	}
}
