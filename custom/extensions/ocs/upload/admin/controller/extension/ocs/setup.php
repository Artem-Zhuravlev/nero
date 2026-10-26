<?php
// Extension: ocs (theme package setup). Admin page + JSON actions that run the packaged migrations inside an existing store.
// Owner: SPEC-040 (REQ-PKG-005..010). Logic lives in system/library/ocs/setup (Ocs\Setup\Service); this controller stays thin.

class ControllerExtensionOcsSetup extends Controller {
	public function index() {
		$this->load->language('extension/ocs/setup');

		$this->document->setTitle($this->language->get('heading_title'));

		$token = 'user_token=' . $this->session->data['user_token'];

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $token . '&type=theme', true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/ocs/setup', $token, true));

		$data['run'] = html_entity_decode($this->url->link('extension/ocs/setup/run', $token, true), ENT_QUOTES, 'UTF-8');
		$data['refresh'] = html_entity_decode($this->url->link('marketplace/modification/refresh', $token, true), ENT_QUOTES, 'UTF-8');
		$data['theme'] = $this->url->link('extension/theme/ocs', $token, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=theme', true);

		$service = $this->service();

		$data['library_missing'] = !$service;
		$data['package_version'] = $service ? $service->version() : '';
		$data['installed_version'] = (string)$this->config->get('ocs_package_version');
		$data['active'] = $this->config->get('config_theme') == 'ocs';
		$data['can_modify'] = $this->user->hasPermission('modify', 'extension/ocs');

		$data['migrations'] = array();

		if ($service && $this->tableReady()) {
			foreach ($service->status() as $row) {
				$data['migrations'][] = $row;
			}
		}

		$data['problems'] = $service ? $service->precheck($this->environment()) : array();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/ocs/setup', $data));
	}

	// POST action=install|activate|demo|catalogue|catalogue_remove|uninstall -> {ok, log[], problems[], refresh}
	public function run() {
		$this->load->language('extension/ocs/setup');

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] != 'POST') {
			$json['error'] = $this->language->get('error_method');
		} elseif (!$this->user->hasPermission('modify', 'extension/ocs')) {
			$json['error'] = $this->language->get('error_permission');
		} elseif (!($service = $this->service())) {
			$json['error'] = $this->language->get('error_library');
		}

		if (!$json) {
			$action = isset($this->request->post['action']) ? $this->request->post['action'] : '';

			try {
				switch ($action) {
					case 'install':
						$json = $service->install($this->environment());
						break;
					case 'activate':
						$json = $service->activate();
						$json['refresh'] = false;
						break;
					case 'demo':
						$json = $service->demo(DIR_IMAGE, $this->models());
						$json['refresh'] = false;
						break;
					case 'catalogue':
						set_time_limit(300);
						$json = $service->createCatalogue($this->models(), DIR_IMAGE);
						break;
					case 'catalogue_remove':
						$json = $service->removeCatalogue($this->models(), DIR_IMAGE);
						break;
					case 'uninstall':
						$json = $service->uninstall();
						break;
					default:
						$json['error'] = $this->language->get('error_action');
				}
			} catch (\Exception $e) {
				$this->log->write('ocs setup: ' . $e->getMessage());
				$json['error'] = $e->getMessage();
			}

			if (!empty($json['refresh'])) {
				$this->clearCache();
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	// Hands the core admin models to the catalogue builder (SPEC-041 REQ-DCAT-002).
	private function models() {
		$that = $this;

		return function ($route) use ($that) {
			$that->load->model($route);

			return $that->{'model_' . str_replace('/', '_', $route)};
		};
	}

	private function service() {
		$base = DIR_SYSTEM . 'library/ocs/setup/';

		if (!is_file($base . 'autoload.php')) {
			return false;
		}

		require_once($base . 'autoload.php');

		$db = new \Ocs\Migrate\CoreDb(DIR_SYSTEM . 'library', DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, defined('DB_PORT') ? (string)DB_PORT : '3306');

		$docroot = rtrim(str_replace('\\', '/', dirname(DIR_APPLICATION)), '/') . '/';

		return new \Ocs\Setup\Service($db, DB_PREFIX, $base . 'payload', function ($app, $rel) use ($docroot) {
			return is_file($docroot . $rel);
		});
	}

	private function tableReady() {
		return (bool)$this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "ocs_migration") . "'")->num_rows;
	}

	private function environment() {
		return array(
			'core_version' => VERSION,
			'php_version'  => PHP_VERSION,
			'writable'     => array(
				DIR_MODIFICATION => is_writable(DIR_MODIFICATION),
				DIR_CACHE        => is_writable(DIR_CACHE),
				DIR_LOGS         => is_writable(DIR_LOGS)
			)
		);
	}

	private function clearCache() {
		foreach ((array)glob(DIR_CACHE . '*') as $file) {
			if (is_file($file) && basename($file) != 'index.html') {
				@unlink($file);
			}
		}
	}
}
