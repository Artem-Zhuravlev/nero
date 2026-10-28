<?php
// Extension: ocs_novaposhta. JSON endpoints of the checkout component: config, city suggestions, points of a city, point selection. Owner: SPEC-085.
// Local data only; no Nova Poshta API call happens here (REQ-NP-050). Responses are JSON, never HTML (REQ-NP-051).

class ControllerExtensionOcsNovaposhtaApi extends Controller {
	public function config() {
		if (!$this->open()) {
			return;
		}

		$strings = $this->load->language('extension/ocs_novaposhta/np');
		$strings = is_array($strings) ? $strings : array();

		$groups = \Ocs\Novaposhta\Presets::groups((string)$this->config->get('shipping_ocs_novaposhta_preset'), $this->config->get('shipping_ocs_novaposhta_selectors'));

		$country = $this->db->query("SELECT country_id FROM " . DB_PREFIX . "country WHERE iso_code_2 = 'UA' AND status = '1'");

		$this->json(array(
			'groups'     => $groups,
			'strings'    => $strings,
			'country_id' => $country->num_rows ? (int)$country->row['country_id'] : 0,
			'only_ua'    => (bool)$this->config->get('shipping_ocs_novaposhta_only_ua'),
			'urls'       => array(
				'cities'     => $this->url->link('extension/ocs_novaposhta/api/cities', '', true),
				'warehouses' => $this->url->link('extension/ocs_novaposhta/api/warehouses', '', true),
				'select'     => $this->url->link('extension/ocs_novaposhta/api/select', '', true)
			)
		), 300);
	}

	public function cities() {
		if (!$this->open(true)) {
			return;
		}

		$q = isset($this->request->get['q']) ? (string)$this->request->get['q'] : '';

		$this->json(array('items' => $this->repository()->cities($q, $this->lang(), 10)), 60);
	}

	public function warehouses() {
		if (!$this->open(true)) {
			return;
		}

		$city = isset($this->request->get['city_ref']) ? (string)$this->request->get['city_ref'] : '';

		if (!$this->isRef($city)) {
			$this->json(array('total' => 0, 'items' => array()), 0);

			return;
		}

		$type = isset($this->request->get['type']) ? (string)$this->request->get['type'] : 'all';
		$q = isset($this->request->get['q']) ? (string)$this->request->get['q'] : '';
		$page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;

		$this->json($this->repository()->warehouses($city, $q, $type, $page, 50, $this->lang()), 60);
	}

	/**
	 * POST city_ref [+ warehouse_ref]: remember the choice in the session. Empty city_ref forgets it.
	 * Replies with what the form should show: city, address line, country and zone to select.
	 */
	public function select() {
		if (!$this->open(true)) {
			return;
		}

		$city = isset($this->request->post['city_ref']) ? (string)$this->request->post['city_ref'] : '';
		$wh = isset($this->request->post['warehouse_ref']) ? (string)$this->request->post['warehouse_ref'] : '';

		if ($city === '') {
			unset($this->session->data['ocs_np']);
			$this->json(array('ok' => true), 0);

			return;
		}

		if (!$this->isRef($city) || ($wh !== '' && !$this->isRef($wh))) {
			$this->json(array('ok' => false), 0);

			return;
		}

		$repository = $this->repository();
		$lang = $this->lang();

		if ($wh !== '') {
			$found = $repository->point($city, $wh);
			$address = $found ? $found['name_' . $lang] : '';
		} else {
			$found = $repository->city($city);
			$address = '';
		}

		if (!$found) {
			$this->json(array('ok' => false), 0);

			return;
		}

		$this->session->data['ocs_np'] = array('city_ref' => $city, 'warehouse_ref' => $wh);

		$country = $this->db->query("SELECT country_id FROM " . DB_PREFIX . "country WHERE iso_code_2 = 'UA' AND status = '1'");
		$country_id = $country->num_rows ? (int)$country->row['country_id'] : 0;
		$zone_id = 0;

		if ($country_id) {
			$zones = $this->db->query("SELECT zone_id, name FROM " . DB_PREFIX . "zone WHERE country_id = '" . $country_id . "' AND status = '1'");
			$zone_id = \Ocs\Novaposhta\Repository::matchZone($found['area_' . $lang] !== '' ? $found['area_' . $lang] : $found['area_uk'], $zones->rows);
		}

		$this->json(array(
			'ok'         => true,
			'city'       => $found['city_' . $lang],
			'address'    => $address,
			'country_id' => $country_id,
			'zone_id'    => $zone_id
		), 0);
	}

	/** Checks the module is on and (for lookups) the visitor is not hammering the endpoint: 120 requests per minute per session. */
	private function open($throttle = false) {
		if (!$this->config->get('shipping_ocs_novaposhta_status')) {
			$this->response->addHeader('HTTP/1.1 404 Not Found');
			$this->response->setOutput('');

			return false;
		}

		if ($throttle) {
			$now = time();
			$rl = isset($this->session->data['ocs_np_rl']) && is_array($this->session->data['ocs_np_rl']) ? $this->session->data['ocs_np_rl'] : array('t' => $now, 'n' => 0);

			if ($now - $rl['t'] >= 60) {
				$rl = array('t' => $now, 'n' => 0);
			}

			$rl['n']++;
			$this->session->data['ocs_np_rl'] = $rl;

			if ($rl['n'] > 120) {
				$this->response->addHeader('HTTP/1.1 429 Too Many Requests');
				$this->response->setOutput('');

				return false;
			}
		}

		return true;
	}

	private function json($data, $max_age) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->addHeader($max_age ? 'Cache-Control: private, max-age=' . (int)$max_age : 'Cache-Control: no-store');
		$this->response->setOutput(json_encode($data));
	}

	private function repository() {
		return new \Ocs\Novaposhta\Repository(new \Ocs\Novaposhta\OcDb($this->db), DB_PREFIX);
	}

	private function lang() {
		return substr((string)$this->config->get('config_language'), 0, 2) == 'ru' ? 'ru' : 'uk';
	}

	private function isRef($value) {
		return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
	}
}
