<?php
// Extension: ocs_novaposhta. Admin settings, directory status and manual sync. Owner: SPEC-085.

class ControllerExtensionShippingOcsNovaposhta extends Controller {
	private $error = array();

	private $entities = array('areas', 'cities', 'warehouses');

	public function index() {
		$this->load->language('extension/shipping/ocs_novaposhta');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('localisation/language');
		$this->load->model('localisation/tax_class');
		$this->load->model('localisation/geo_zone');

		$token = 'user_token=' . $this->session->data['user_token'];

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$post = $this->request->post;

			if (trim((string)$post['shipping_ocs_novaposhta_api_key']) === '') {
				$post['shipping_ocs_novaposhta_api_key'] = (string)$this->config->get('shipping_ocs_novaposhta_api_key');
			}

			if (trim((string)$post['shipping_ocs_novaposhta_cron_token']) === '' || !empty($post['regenerate_token'])) {
				$post['shipping_ocs_novaposhta_cron_token'] = bin2hex(random_bytes(16));
			}

			unset($post['regenerate_token']);

			$post['shipping_ocs_novaposhta_selectors'] = \Ocs\Novaposhta\Presets::parse(isset($post['shipping_ocs_novaposhta_selectors']) ? $post['shipping_ocs_novaposhta_selectors'] : '');
			$post['shipping_ocs_novaposhta_batch_size'] = max(10, min(500, (int)$post['shipping_ocs_novaposhta_batch_size']));
			$post['shipping_ocs_novaposhta_pause_ms'] = max(0, min(5000, (int)$post['shipping_ocs_novaposhta_pause_ms']));
			$post['shipping_ocs_novaposhta_cost'] = (float)str_replace(',', '.', $post['shipping_ocs_novaposhta_cost']);
			$post['shipping_ocs_novaposhta_free_total'] = (float)str_replace(',', '.', $post['shipping_ocs_novaposhta_free_total']);

			$this->model_setting_setting->editSetting('shipping_ocs_novaposhta', $post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', $token . '&type=shipping', true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['error_key'] = isset($this->error['key']) ? $this->error['key'] : '';

		$data['breadcrumbs'] = array(
			array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true)),
			array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $token . '&type=shipping', true)),
			array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/shipping/ocs_novaposhta', $token, true))
		);

		$data['action'] = $this->url->link('extension/shipping/ocs_novaposhta', $token, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=shipping', true);
		$data['sync_url'] = html_entity_decode($this->url->link('extension/shipping/ocs_novaposhta/sync', $token, true), ENT_QUOTES, 'UTF-8');

		$defaults = array('status' => 0, 'sort_order' => 1, 'api_key' => '', 'cron_token' => '', 'cost' => '70', 'free_total' => '0', 'tax_class_id' => 0, 'geo_zone_id' => 0,
			'only_ua' => 1, 'require_point' => 1, 'preset' => 'default', 'batch_size' => 500, 'pause_ms' => 200);

		foreach ($defaults as $field => $default) {
			$key = 'shipping_ocs_novaposhta_' . $field;

			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} elseif ($this->config->has($key)) {
				$data[$key] = $this->config->get($key);
			} else {
				$data[$key] = $default;
			}
		}

		// the key is write-only: the form shows whether it is set, never the value
		$data['api_key_set'] = (string)$this->config->get('shipping_ocs_novaposhta_api_key') !== '';
		$data['shipping_ocs_novaposhta_api_key'] = '';

		$titles = isset($this->request->post['shipping_ocs_novaposhta_title']) ? $this->request->post['shipping_ocs_novaposhta_title'] : (array)$this->config->get('shipping_ocs_novaposhta_title');
		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['titles'] = array();

		foreach ($data['languages'] as $language) {
			$data['titles'][$language['code']] = isset($titles[$language['code']]) ? $titles[$language['code']] : '';
		}

		$selectors = isset($this->request->post['shipping_ocs_novaposhta_selectors']) ? $this->request->post['shipping_ocs_novaposhta_selectors'] : \Ocs\Novaposhta\Presets::format((array)$this->config->get('shipping_ocs_novaposhta_selectors'));
		$data['selectors_text'] = $selectors;

		$data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$data['directories'] = $this->directories();

		$root = rtrim(str_replace('\\', '/', dirname(DIR_APPLICATION)), '/');
		$catalog = defined('HTTPS_CATALOG') ? HTTPS_CATALOG : HTTP_CATALOG;
		$data['cli_path'] = $root . '/system/library/ocs/novaposhta/bin/sync.php';
		$data['cron_url'] = $catalog . 'index.php?route=extension/ocs_novaposhta/cron&key=' . $data['shipping_ocs_novaposhta_cron_token'] . '&entity=';

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/shipping/ocs_novaposhta', $data));
	}

	/**
	 * AJAX: run one portion (<= 20 s) of a directory, or reset its cursor (POST reset=1).
	 */
	public function sync() {
		$this->load->language('extension/shipping/ocs_novaposhta');

		$json = array();

		if (!$this->user->hasPermission('modify', 'extension/shipping/ocs_novaposhta')) {
			$json['error'] = $this->language->get('error_permission');
		} elseif ($this->request->server['REQUEST_METHOD'] != 'POST' || !isset($this->request->post['entity']) || !in_array($this->request->post['entity'], $this->entities, true)) {
			$json['error'] = $this->language->get('error_entity');
		} else {
			$entity = $this->request->post['entity'];

			@set_time_limit(40);

			$sync = new \Ocs\Novaposhta\Sync(
				new \Ocs\Novaposhta\OcDb($this->db),
				DB_PREFIX,
				new \Ocs\Novaposhta\Client($this->config->get('shipping_ocs_novaposhta_api_key')),
				array('limit' => (int)$this->config->get('shipping_ocs_novaposhta_batch_size'), 'pause_ms' => (int)$this->config->get('shipping_ocs_novaposhta_pause_ms'))
			);

			if (!empty($this->request->post['reset'])) {
				$sync->reset($entity);
				$json['status'] = 'idle';
			} else {
				$result = $sync->run($entity, 20);
				$json['status'] = $result['status'];
				$json['error'] = $result['error'];
			}

			$json['directory'] = $this->describe($entity);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function directories() {
		$out = array();

		foreach ($this->entities as $entity) {
			$out[] = $this->describe($entity);
		}

		return $out;
	}

	private function describe($entity) {
		$state = array('status' => 'idle', 'finished_at' => 0, 'rows_total' => 0, 'rows_run' => 0, 'page' => 1, 'error' => '');

		try {
			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ocs_np_sync` WHERE entity = '" . $this->db->escape($entity) . "'");

			if ($query->num_rows) {
				$state = $query->row;
			}

			$table = DB_PREFIX . \Ocs\Novaposhta\Sync::$tables[$entity];
			$count = $this->db->query("SELECT COUNT(*) AS total, SUM(is_active) AS active FROM `" . $table . "`");
			$total = (int)$count->row['total'];
			$active = (int)$count->row['active'];
		} catch (\Exception $e) {
			$total = 0;
			$active = 0;
		}

		return array(
			'entity'   => $entity,
			'name'     => $this->language->get('text_entity_' . $entity),
			'status'   => $state['status'],
			'finished' => $state['finished_at'] ? date($this->language->get('datetime_format'), (int)$state['finished_at']) : '—',
			'active'   => $active,
			'total'    => $total,
			'page'     => (int)$state['page'],
			'rows_run' => (int)$state['rows_run'],
			'error'    => $state['error']
		);
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/shipping/ocs_novaposhta')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$post = $this->request->post;

		if (!empty($post['shipping_ocs_novaposhta_status']) && trim((string)$post['shipping_ocs_novaposhta_api_key']) === '' && !$this->config->get('shipping_ocs_novaposhta_api_key')) {
			$this->error['key'] = $this->language->get('error_key');
		}

		if (!in_array(isset($post['shipping_ocs_novaposhta_preset']) ? $post['shipping_ocs_novaposhta_preset'] : '', array('default', 'simple', 'custom'), true)) {
			$this->error['warning'] = $this->language->get('error_form');
		}

		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('error_form');
		}

		return !$this->error;
	}
}
