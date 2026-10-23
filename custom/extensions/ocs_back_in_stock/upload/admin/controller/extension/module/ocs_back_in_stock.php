<?php
// Extension: ocs_back_in_stock. Admin settings, subscription list, delete and CSV export. Owner: SPEC-025 (REQ-QOR-022).

class ControllerExtensionModuleOcsBackInStock extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/ocs_back_in_stock');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		$token = 'user_token=' . $this->session->data['user_token'];

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_ocs_back_in_stock', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', $token . '&type=module', true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $token . '&type=module', true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/ocs_back_in_stock', $token, true));

		$data['action'] = $this->url->link('extension/module/ocs_back_in_stock', $token, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=module', true);
		$data['subscriptions'] = $this->url->link('extension/module/ocs_back_in_stock/subscriptions', $token, true);

		if (isset($this->request->post['module_ocs_back_in_stock_status'])) {
			$data['module_ocs_back_in_stock_status'] = $this->request->post['module_ocs_back_in_stock_status'];
		} else {
			$data['module_ocs_back_in_stock_status'] = $this->config->get('module_ocs_back_in_stock_status');
		}

		$data['version'] = '1.0.0';

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_back_in_stock', $data));
	}

	public function subscriptions() {
		$this->load->language('extension/module/ocs_back_in_stock');

		$this->document->setTitle($this->language->get('heading_subscriptions'));

		$token = 'user_token=' . $this->session->data['user_token'];
		$page = isset($this->request->get['page']) ? max(1, (int)$this->request->get['page']) : 1;

		$data['success'] = '';
		$data['error_warning'] = '';

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && isset($this->request->post['delete_id'])) {
			if ($this->user->hasPermission('modify', 'extension/module/ocs_back_in_stock')) {
				$this->db->query("DELETE FROM " . DB_PREFIX . "ocs_back_in_stock WHERE subscription_id = '" . (int)$this->request->post['delete_id'] . "'");

				$data['success'] = $this->language->get('text_saved');
			} else {
				$data['error_warning'] = $this->language->get('error_permission');
			}
		}

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('heading_subscriptions'), 'href' => $this->url->link('extension/module/ocs_back_in_stock/subscriptions', $token, true));

		$data['rows'] = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "ocs_back_in_stock ORDER BY subscription_id DESC LIMIT " . (($page - 1) * 50) . ",50");

		foreach ($query->rows as $row) {
			$data['rows'][] = array(
				'id'       => $row['subscription_id'],
				'product'  => $row['product_name'],
				'href'     => $this->url->link('catalog/product/edit', $token . '&product_id=' . (int)$row['product_id'], true),
				'options'  => $row['option_key'],
				'email'    => $row['email'],
				'language' => $row['language_code'],
				'status'   => $row['status'] ? $this->language->get('text_notified') : $this->language->get('text_waiting'),
				'date'     => date($this->language->get('datetime_format'), strtotime($row['date_added']))
			);
		}

		$pagination = new Pagination();
		$pagination->total = (int)$this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "ocs_back_in_stock")->row['total'];
		$pagination->page = $page;
		$pagination->limit = 50;
		$pagination->url = $this->url->link('extension/module/ocs_back_in_stock/subscriptions', $token . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['action'] = $this->url->link('extension/module/ocs_back_in_stock/subscriptions', $token . '&page=' . $page, true);
		$data['export'] = $this->url->link('extension/module/ocs_back_in_stock/export', $token, true);
		$data['back'] = $this->url->link('extension/module/ocs_back_in_stock', $token, true);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ocs_back_in_stock_subscriptions', $data));
	}

	/**
	 * CSV export (semicolon separated, UTF-8 with BOM for spreadsheet programs).
	 */
	public function export() {
		if (!$this->user->hasPermission('access', 'extension/module/ocs_back_in_stock')) {
			$this->response->redirect($this->url->link('error/permission', 'user_token=' . $this->session->data['user_token'], true));
		}

		$out = "\xEF\xBB\xBF" . "email;product;options;language;status;date_added\r\n";

		foreach ($this->db->query("SELECT * FROM " . DB_PREFIX . "ocs_back_in_stock ORDER BY subscription_id")->rows as $row) {
			$cells = array($row['email'], $row['product_name'], $row['option_key'], $row['language_code'], $row['status'], $row['date_added']);

			foreach ($cells as $i => $cell) {
				$cell = (string)$cell;

				// neutralise spreadsheet formulas in user-supplied cells
				if ($cell !== '' && strpos('=+-@', $cell[0]) !== false) {
					$cell = "'" . $cell;
				}

				$cells[$i] = '"' . str_replace('"', '""', $cell) . '"';
			}

			$out .= implode(';', $cells) . "\r\n";
		}

		$this->response->addHeader('Content-Type: text/csv; charset=utf-8');
		$this->response->addHeader('Content-Disposition: attachment; filename="back-in-stock.csv"');
		$this->response->setOutput($out);
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/ocs_back_in_stock')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
