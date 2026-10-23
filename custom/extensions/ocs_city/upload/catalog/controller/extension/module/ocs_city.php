<?php
// Extension: ocs_city. City selector popup, delivery block, stores page, order tracking page. Owner: SPEC-032.

class ControllerExtensionModuleOcsCity extends Controller {
	/**
	 * GET: HTML body of the city popup (OCS.modal).
	 */
	public function select() {
		if (!$this->config->get('module_ocs_city_status')) {
			$this->response->setOutput('');

			return;
		}

		$this->load->language('extension/module/ocs_city');
		$this->load->model('extension/ocs_city/city');
		$this->load->model('extension/ocs_city/block');

		$current = $this->model_extension_ocs_city_block->currentCity();
		$data['cities'] = array();

		foreach ($this->model_extension_ocs_city_city->cities() as $row) {
			$data['cities'][] = array('city_id' => (int)$row['city_id'], 'name' => htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'), 'current' => (int)$row['city_id'] === $current);
		}

		$data['title'] = $this->language->get('select_title');
		$data['search'] = $this->language->get('select_search');
		$data['text_close'] = $this->language->get('text_close');

		$this->response->setOutput($this->load->view('extension/module/ocs_city_select', $data));
	}

	/**
	 * GET product_id, city_id: the delivery block for the chosen city (the cookie is set by theme.js at the same time).
	 */
	public function block() {
		$out = '';

		if ($this->config->get('module_ocs_city_status')) {
			$this->load->model('extension/ocs_city/block');

			$city_id = isset($this->request->get['city_id']) ? (int)$this->request->get['city_id'] : $this->model_extension_ocs_city_block->currentCity();
			$data = $this->model_extension_ocs_city_block->data(isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0, $city_id);

			if ($data) {
				$out = $this->load->view('extension/module/ocs_city_block', $data);
			}
		}

		$this->response->setOutput($out);
	}

	/**
	 * Stores page, filtered by city.
	 */
	public function stores() {
		if (!$this->config->get('module_ocs_city_status')) {
			$this->response->redirect($this->url->link('common/home'));
		}

		$this->load->language('extension/module/ocs_city');
		$this->load->model('extension/ocs_city/city');
		$this->load->model('extension/ocs_city/block');

		$city_id = isset($this->request->get['city_id']) ? (int)$this->request->get['city_id'] : $this->model_extension_ocs_city_block->currentCity();

		$this->document->setTitle($this->language->get('stores_title'));

		$data['cities'] = array();

		foreach ($this->model_extension_ocs_city_city->cities() as $row) {
			$data['cities'][] = array('name' => htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'), 'href' => $this->url->link('extension/module/ocs_city/stores', 'city_id=' . (int)$row['city_id']), 'current' => (int)$row['city_id'] === $city_id);
		}

		$data['stores'] = array();

		foreach ($this->model_extension_ocs_city_city->stores($city_id) as $store) {
			$data['stores'][] = array(
				'name'    => htmlspecialchars($store['name'], ENT_QUOTES, 'UTF-8'),
				'address' => htmlspecialchars($store['address'], ENT_QUOTES, 'UTF-8'),
				'hours'   => htmlspecialchars($store['hours'], ENT_QUOTES, 'UTF-8'),
				'phone'   => htmlspecialchars($store['phone'], ENT_QUOTES, 'UTF-8'),
				'tel'     => preg_replace('/[^\d+]/', '', $store['phone']),
				'map'     => htmlspecialchars(\Ocs\City::safeLink($store['map_link']), ENT_QUOTES, 'UTF-8')
			);
		}

		foreach (array('stores_title', 'stores_empty', 'text_hours', 'text_map') as $key) {
			$data[$key] = $this->language->get($key);
		}

		$this->page($data, 'extension/module/ocs_city_stores');
	}

	/**
	 * Order tracking: order number + e-mail or phone; the same generic answer for every mismatch (REQ-CTY-041).
	 */
	public function track() {
		if (!$this->config->get('module_ocs_city_status') || !$this->config->get('module_ocs_city_tracking')) {
			$this->response->redirect($this->url->link('common/home'));
		}

		$this->load->language('extension/module/ocs_city');
		$this->load->model('extension/ocs_request/guard');
		$this->load->model('extension/ocs_city/city');

		$guard = $this->model_extension_ocs_request_guard;

		$this->document->setTitle($this->language->get('track_title'));

		$data['error'] = '';
		$data['result'] = false;
		$data['order_id'] = '';
		$data['contact'] = '';

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			$order_id = isset($this->request->post['order_id']) ? (int)$this->request->post['order_id'] : 0;
			$contact = isset($this->request->post['contact']) ? html_entity_decode((string)$this->request->post['contact'], ENT_QUOTES, 'UTF-8') : '';

			$data['order_id'] = $order_id ? $order_id : '';
			$data['contact'] = htmlspecialchars($contact, ENT_QUOTES, 'UTF-8');

			if (!$guard->tokenValid(isset($this->request->post['token']) ? $this->request->post['token'] : '')) {
				$data['error'] = $this->language->get('error_token');
			} elseif (!$guard->allow('track')) {
				$data['error'] = $this->language->get('error_rate');
			} else {
				$order = $order_id > 0 ? $this->model_extension_ocs_city_city->order($order_id) : array();

				if ($order && \Ocs\City::trackingMatches($order, $contact)) {
					$history = array();

					foreach ($this->model_extension_ocs_city_city->history($order_id) as $row) {
						$history[] = array('status' => htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'), 'date' => date($this->language->get('datetime_format'), strtotime($row['date_added'])));
					}

					$last = $history ? $history[count($history) - 1]['status'] : '';

					$data['result'] = array('order_id' => (int)$order['order_id'], 'status' => $last, 'history' => $history, 'date' => date($this->language->get('date_format_short'), strtotime($order['date_added'])));
				} else {
					// unknown order and wrong second factor look the same
					$data['error'] = $this->language->get('track_not_found');
				}
			}
		}

		$data['token'] = $guard->token();
		$data['action'] = $this->url->link('extension/module/ocs_city/track', '', true);

		foreach (array('track_title', 'track_intro', 'track_order', 'track_contact', 'track_button', 'track_status', 'track_history', 'track_placed') as $key) {
			$data[$key] = $this->language->get($key);
		}

		$this->page($data, 'extension/module/ocs_city_track');
	}

	private function page($data, $template) {
		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/home'));
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view($template, $data));
	}
}
