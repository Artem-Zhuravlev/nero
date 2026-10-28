<?php
// Extension: ocs_novaposhta. Shipping quote "Nova Poshta: branch / postomat". Owner: SPEC-085.
// The quote shows the point chosen in checkout (session `ocs_np`, re-checked against the local directory on every call).
// Without a point the method is listed with an explanatory error, so it cannot be selected (setting require_point, REQ-NP-021).

class ModelExtensionShippingOcsNovaposhta extends Model {
	public function getQuote($address) {
		$this->load->language('extension/shipping/ocs_novaposhta');

		$geo_zone_id = (int)$this->config->get('shipping_ocs_novaposhta_geo_zone_id');

		if ($geo_zone_id) {
			$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . $geo_zone_id . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

			if (!$query->num_rows) {
				return array();
			}
		}

		if ($this->config->get('shipping_ocs_novaposhta_only_ua')) {
			$query = $this->db->query("SELECT iso_code_2 FROM " . DB_PREFIX . "country WHERE country_id = '" . (int)$address['country_id'] . "'");

			if (!$query->num_rows || $query->row['iso_code_2'] != 'UA') {
				return array();
			}
		}

		$lang = $this->lang();
		$title = $this->methodTitle();

		$point = $this->selectedPoint();

		$cost = (float)$this->config->get('shipping_ocs_novaposhta_cost');
		$free = (float)$this->config->get('shipping_ocs_novaposhta_free_total');

		if ($free > 0 && $this->cart->getSubTotal() >= $free) {
			$cost = 0.0;
		}

		$quote_data = array();
		$error = false;

		if ($point || !$this->config->get('shipping_ocs_novaposhta_require_point')) {
			$quote_data['point'] = array(
				'code'         => 'ocs_novaposhta.point',
				'title'        => $point ? $title . ': ' . $point['name_' . $lang] : $title,
				'cost'         => $cost,
				'tax_class_id' => $this->config->get('shipping_ocs_novaposhta_tax_class_id'),
				'text'         => $cost > 0 ? $this->currency->format($this->tax->calculate($cost, $this->config->get('shipping_ocs_novaposhta_tax_class_id'), $this->config->get('config_tax')), $this->session->data['currency']) : $this->language->get('text_free')
			);
		} else {
			$error = $this->language->get('error_point');
		}

		return array(
			'code'       => 'ocs_novaposhta',
			'title'      => $title,
			'quote'      => $quote_data,
			'sort_order' => $this->config->get('shipping_ocs_novaposhta_sort_order'),
			'error'      => $error
		);
	}

	/** 'ru' for Russian storefronts, 'uk' for everything else (Nova Poshta names exist in these two). */
	public function lang() {
		return substr((string)$this->config->get('config_language'), 0, 2) == 'ru' ? 'ru' : 'uk';
	}

	/** Method name: the per-language setting, else the language file. */
	public function methodTitle() {
		$titles = (array)$this->config->get('shipping_ocs_novaposhta_title');
		$code = $this->config->get('config_language');

		if (!empty($titles[$code])) {
			return $titles[$code];
		}

		$this->load->language('extension/shipping/ocs_novaposhta');

		return $this->language->get('text_title');
	}

	/**
	 * The point stored in the session, or null when none or no longer valid (closed, moved to another city).
	 *
	 * @return array|null
	 */
	public function selectedPoint() {
		$sel = isset($this->session->data['ocs_np']) && is_array($this->session->data['ocs_np']) ? $this->session->data['ocs_np'] : array();

		if (empty($sel['city_ref']) || empty($sel['warehouse_ref'])) {
			return null;
		}

		$repository = new \Ocs\Novaposhta\Repository(new \Ocs\Novaposhta\OcDb($this->db), DB_PREFIX);

		return $repository->point($sel['city_ref'], $sel['warehouse_ref']);
	}
}
