<?php
// Extension: ocs_city. Data of the product page delivery block for one city. Owner: SPEC-032 (REQ-CTY-010, 011, 021).

class ModelExtensionOcsCityBlock extends Model {
	/**
	 * Current city id: the ocs_city cookie when it is an active city, else the default one.
	 */
	public function currentCity() {
		$this->load->model('extension/ocs_city/city');

		$ids = array();
		$default = 0;

		foreach ($this->model_extension_ocs_city_city->cities() as $row) {
			$ids[] = (int)$row['city_id'];

			if ($row['is_default']) {
				$default = (int)$row['city_id'];
			}
		}

		return \Ocs\City::current(isset($this->request->cookie['ocs_city']) ? $this->request->cookie['ocs_city'] : '', $ids, $default);
	}

	/**
	 * @return array|false false when the block must stay hidden (no city, no rules)
	 */
	public function data($product_id, $city_id) {
		$this->load->language('extension/module/ocs_city');
		$this->load->model('extension/ocs_city/city');
		$this->load->model('catalog/product');

		$model = $this->model_extension_ocs_city_city;
		$city_name = '';

		foreach ($model->cities() as $row) {
			if ((int)$row['city_id'] === (int)$city_id) {
				$city_name = $row['name'];
			}
		}

		$rules = $city_name !== '' ? $model->rules($city_id) : array();
		$product = $this->model_catalog_product->getProduct((int)$product_id);

		if (!$rules || !$product) {
			return false;
		}

		$amount = !is_null($product['special']) ? (float)$product['special'] : (float)$product['price'];
		$now = date('H:i');
		$currency = $this->session->data['currency'];
		$lines = array();

		foreach ($rules as $rule) {
			$price = \Ocs\City::price($rule, $amount);
			$eta = \Ocs\City::eta($rule, $now);

			if ($price['free']) {
				$price_text = $this->language->get('text_free');
			} else {
				$price_text = $this->currency->format($price['price'], $currency);

				if ($price['free_from'] > 0) {
					$price_text .= ', ' . sprintf($this->language->get('text_free_from'), $this->currency->format($price['free_from'], $currency));
				}
			}

			if ($eta['type'] == 'tomorrow') {
				$eta_text = $this->language->get('text_tomorrow');
			} elseif ($eta['type'] == 'days') {
				$eta_text = $eta['min'] == $eta['max'] ? sprintf($this->language->get('text_days_one'), $eta['max']) : sprintf($this->language->get('text_days'), $eta['min'], $eta['max']);
			} else {
				$eta_text = '';
			}

			$lines[] = array(
				'label' => $this->language->get('method_' . $rule['method']),
				'price' => $price_text,
				'eta'   => $eta_text,
				'note'  => htmlspecialchars($rule['note'], ENT_QUOTES, 'UTF-8')
			);
		}

		$out = array(
			'city'            => htmlspecialchars($city_name, ENT_QUOTES, 'UTF-8'),
			'lines'           => $lines,
			'pickup'          => $this->pickup($product, $city_id),
			'infos'           => array(),
			'text_title'      => $this->language->get('text_delivery_title'),
			'text_city'       => $this->language->get('text_your_city'),
			'text_change'     => $this->language->get('text_change_city'),
			'select_url'      => $this->url->link('extension/module/ocs_city/select', '', true),
			'stores_url'      => $this->url->link('extension/module/ocs_city/stores', 'city_id=' . (int)$city_id, true),
			'text_stores'     => $this->language->get('text_all_stores'),
			'block_url'       => $this->url->link('extension/module/ocs_city/block', 'product_id=' . (int)$product_id, true),
			'product_id'      => (int)$product_id
		);

		$texts = (array)$this->config->get('module_ocs_city_l');
		$texts = isset($texts[$this->config->get('config_language')]) && is_array($texts[$this->config->get('config_language')]) ? $texts[$this->config->get('config_language')] : array();

		foreach (array('payment', 'warranty', 'returns') as $field) {
			$text = isset($texts[$field]) ? trim(html_entity_decode((string)$texts[$field], ENT_QUOTES, 'UTF-8')) : '';

			if ($text !== '') {
				$out['infos'][] = array('label' => $this->language->get('text_' . $field), 'text' => htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
			}
		}

		return $out;
	}

	/**
	 * REQ-CTY-021: per-store stock when the product has such data, else one store-level line from the product quantity; '' = nothing to say.
	 */
	private function pickup($product, $city_id) {
		$stores = $this->model_extension_ocs_city_city->stores($city_id);

		if (!$stores) {
			return '';
		}

		$stock = $this->model_extension_ocs_city_city->stock($product['product_id']);

		if ($stock) {
			$names = array();

			foreach ($stores as $store) {
				if (!empty($stock[(int)$store['store_id']])) {
					$names[] = htmlspecialchars($store['name'], ENT_QUOTES, 'UTF-8');
				}
			}

			return $names ? sprintf($this->language->get('text_in_stores'), implode(', ', $names)) : '';
		}

		if ((int)$product['quantity'] <= 0) {
			return '';
		}

		$minutes = (int)$this->config->get('module_ocs_city_pickup_minutes');

		return $minutes > 0 ? sprintf($this->language->get('text_pickup_minutes'), $minutes) : $this->language->get('text_pickup_ok');
	}
}
