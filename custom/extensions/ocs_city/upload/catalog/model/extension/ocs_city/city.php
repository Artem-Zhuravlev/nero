<?php
// Extension: ocs_city. Storefront data: cities, delivery rules, stores, store stock, order lookup. Owner: SPEC-032.
// Cities, rules and stores are cached per language (REQ-CTY-040); the admin clears the "ocs_city" cache on every save.

class ModelExtensionOcsCityCity extends Model {
	public function cities() {
		$lang = (int)$this->config->get('config_language_id');
		$key = 'ocs_city.cities.' . $lang;

		$rows = $this->cache->get($key);

		if (!is_array($rows)) {
			$rows = $this->db->query("SELECT c.city_id, c.is_default, cd.name FROM `" . DB_PREFIX . "ocs_city` c INNER JOIN `" . DB_PREFIX . "ocs_city_description` cd ON (cd.city_id = c.city_id AND cd.language_id = '" . $lang . "') WHERE c.status = '1' AND cd.name <> '' ORDER BY c.sort_order ASC, cd.name ASC")->rows;

			$this->cache->set($key, $rows);
		}

		return $rows;
	}

	public function rules($city_id) {
		$lang = (int)$this->config->get('config_language_id');
		$key = 'ocs_city.rules.' . $lang . '.' . (int)$city_id;

		$rules = $this->cache->get($key);

		if (!is_array($rules)) {
			$rules = array();

			$query = $this->db->query("SELECT d.*, dd.note FROM `" . DB_PREFIX . "ocs_city_delivery` d LEFT JOIN `" . DB_PREFIX . "ocs_city_delivery_description` dd ON (dd.city_id = d.city_id AND dd.method = d.method AND dd.language_id = '" . $lang . "') WHERE d.city_id = '" . (int)$city_id . "' AND d.status = '1'");

			foreach ($query->rows as $row) {
				$rules[$row['method']] = array(
					'method'    => $row['method'],
					'price'     => (float)$row['price'],
					'free_from' => (float)$row['free_from'],
					'days_min'  => (int)$row['days_min'],
					'days_max'  => (int)$row['days_max'],
					'cutoff'    => $row['cutoff'],
					'note'      => (string)$row['note']
				);
			}

			$ordered = array();

			foreach (\Ocs\City::METHODS as $method) {
				if (isset($rules[$method])) {
					$ordered[] = $rules[$method];
				}
			}

			$rules = $ordered;

			$this->cache->set($key, $rules);
		}

		return $rules;
	}

	public function stores($city_id = 0) {
		$lang = (int)$this->config->get('config_language_id');
		$key = 'ocs_city.stores.' . $lang . '.' . (int)$city_id;

		$stores = $this->cache->get($key);

		if (!is_array($stores)) {
			$sql = "SELECT s.*, sd.name, sd.address, sd.hours FROM `" . DB_PREFIX . "ocs_store` s INNER JOIN `" . DB_PREFIX . "ocs_store_description` sd ON (sd.store_id = s.store_id AND sd.language_id = '" . $lang . "') WHERE s.status = '1'";

			if ($city_id) {
				$sql .= " AND s.city_id = '" . (int)$city_id . "'";
			}

			$stores = $this->db->query($sql . " ORDER BY s.sort_order ASC, sd.name ASC")->rows;

			$this->cache->set($key, $stores);
		}

		return $stores;
	}

	/**
	 * store_id => quantity for the product; empty when no per-store data exists for it.
	 */
	public function stock($product_id) {
		$out = array();

		foreach ($this->db->query("SELECT store_id, quantity FROM `" . DB_PREFIX . "ocs_store_stock` WHERE product_id = '" . (int)$product_id . "'")->rows as $row) {
			$out[(int)$row['store_id']] = (int)$row['quantity'];
		}

		return $out;
	}

	public function order($order_id) {
		return $this->db->query("SELECT o.order_id, o.email, o.telephone, o.order_status_id, o.date_added, o.total, o.currency_code, o.currency_value FROM `" . DB_PREFIX . "order` o WHERE o.order_id = '" . (int)$order_id . "' AND o.order_status_id > '0'")->row;
	}

	/**
	 * Status names and dates only: comments of the staff are not shown.
	 */
	public function history($order_id) {
		return $this->db->query("SELECT os.name, oh.date_added FROM `" . DB_PREFIX . "order_history` oh INNER JOIN `" . DB_PREFIX . "order_status` os ON (os.order_status_id = oh.order_status_id AND os.language_id = '" . (int)$this->config->get('config_language_id') . "') WHERE oh.order_id = '" . (int)$order_id . "' AND oh.order_status_id > '0' ORDER BY oh.order_history_id ASC")->rows;
	}
}
