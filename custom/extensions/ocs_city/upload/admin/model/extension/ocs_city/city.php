<?php
// Extension: ocs_city. Admin CRUD of cities, delivery rules and stores. Owner: SPEC-032.
// Values are stored raw (the controller decodes the entity-encoded request) and escaped on output.

class ModelExtensionOcsCityCity extends Model {
	public function getCities() {
		return $this->db->query("SELECT c.*, (SELECT name FROM `" . DB_PREFIX . "ocs_city_description` d WHERE d.city_id = c.city_id AND d.language_id = '" . (int)$this->config->get('config_language_id') . "') AS name FROM `" . DB_PREFIX . "ocs_city` c ORDER BY c.sort_order ASC, c.city_id ASC")->rows;
	}

	public function getCity($city_id) {
		$city = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ocs_city` WHERE city_id = '" . (int)$city_id . "'")->row;

		if (!$city) {
			return array();
		}

		$city['names'] = array();

		foreach ($this->db->query("SELECT language_id, name FROM `" . DB_PREFIX . "ocs_city_description` WHERE city_id = '" . (int)$city_id . "'")->rows as $row) {
			$city['names'][(int)$row['language_id']] = $row['name'];
		}

		$city['rules'] = array();

		foreach ($this->db->query("SELECT * FROM `" . DB_PREFIX . "ocs_city_delivery` WHERE city_id = '" . (int)$city_id . "'")->rows as $row) {
			$city['rules'][$row['method']] = $row + array('notes' => array());
		}

		foreach ($this->db->query("SELECT method, language_id, note FROM `" . DB_PREFIX . "ocs_city_delivery_description` WHERE city_id = '" . (int)$city_id . "'")->rows as $row) {
			if (isset($city['rules'][$row['method']])) {
				$city['rules'][$row['method']]['notes'][(int)$row['language_id']] = $row['note'];
			}
		}

		return $city;
	}

	/**
	 * @param array $d sort_order, is_default, status, names[language_id], rules[method] => array(status, price, free_from, days_min, days_max, cutoff, notes[language_id])
	 */
	public function saveCity($city_id, $d) {
		if ($city_id) {
			$this->db->query("UPDATE `" . DB_PREFIX . "ocs_city` SET sort_order = '" . (int)$d['sort_order'] . "', is_default = '" . (int)$d['is_default'] . "', status = '" . (int)$d['status'] . "' WHERE city_id = '" . (int)$city_id . "'");
		} else {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "ocs_city` SET sort_order = '" . (int)$d['sort_order'] . "', is_default = '" . (int)$d['is_default'] . "', status = '" . (int)$d['status'] . "'");

			$city_id = $this->db->getLastId();
		}

		if ($d['is_default']) {
			$this->db->query("UPDATE `" . DB_PREFIX . "ocs_city` SET is_default = '0' WHERE city_id <> '" . (int)$city_id . "'");
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "ocs_city_description` WHERE city_id = '" . (int)$city_id . "'");

		foreach ($d['names'] as $language_id => $name) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "ocs_city_description` SET city_id = '" . (int)$city_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($name) . "'");
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "ocs_city_delivery` WHERE city_id = '" . (int)$city_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "ocs_city_delivery_description` WHERE city_id = '" . (int)$city_id . "'");

		foreach ($d['rules'] as $method => $r) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "ocs_city_delivery` SET city_id = '" . (int)$city_id . "', method = '" . $this->db->escape($method) . "', price = '" . (float)$r['price'] . "', free_from = '" . (float)$r['free_from'] . "', days_min = '" . (int)$r['days_min'] . "', days_max = '" . (int)$r['days_max'] . "', cutoff = '" . $this->db->escape($r['cutoff']) . "', status = '" . (int)$r['status'] . "'");

			foreach ($r['notes'] as $language_id => $note) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "ocs_city_delivery_description` SET city_id = '" . (int)$city_id . "', method = '" . $this->db->escape($method) . "', language_id = '" . (int)$language_id . "', note = '" . $this->db->escape($note) . "'");
			}
		}

		$this->cache->delete('ocs_city');

		return $city_id;
	}

	public function deleteCity($city_id) {
		foreach (array('ocs_city', 'ocs_city_description', 'ocs_city_delivery', 'ocs_city_delivery_description') as $table) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . $table . "` WHERE city_id = '" . (int)$city_id . "'");
		}

		foreach ($this->db->query("SELECT store_id FROM `" . DB_PREFIX . "ocs_store` WHERE city_id = '" . (int)$city_id . "'")->rows as $row) {
			$this->deleteStore($row['store_id']);
		}

		$this->cache->delete('ocs_city');
	}

	public function getStores() {
		return $this->db->query("SELECT s.*, (SELECT name FROM `" . DB_PREFIX . "ocs_store_description` d WHERE d.store_id = s.store_id AND d.language_id = '" . (int)$this->config->get('config_language_id') . "') AS name, (SELECT name FROM `" . DB_PREFIX . "ocs_city_description` cd WHERE cd.city_id = s.city_id AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS city FROM `" . DB_PREFIX . "ocs_store` s ORDER BY s.city_id ASC, s.sort_order ASC, s.store_id ASC")->rows;
	}

	public function getStore($store_id) {
		$store = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ocs_store` WHERE store_id = '" . (int)$store_id . "'")->row;

		if (!$store) {
			return array();
		}

		$store['desc'] = array();

		foreach ($this->db->query("SELECT * FROM `" . DB_PREFIX . "ocs_store_description` WHERE store_id = '" . (int)$store_id . "'")->rows as $row) {
			$store['desc'][(int)$row['language_id']] = $row;
		}

		return $store;
	}

	public function saveStore($store_id, $d) {
		$set = "city_id = '" . (int)$d['city_id'] . "', phone = '" . $this->db->escape($d['phone']) . "', map_link = '" . $this->db->escape($d['map_link']) . "', latitude = '" . $this->db->escape($d['latitude']) . "', longitude = '" . $this->db->escape($d['longitude']) . "', sort_order = '" . (int)$d['sort_order'] . "', status = '" . (int)$d['status'] . "'";

		if ($store_id) {
			$this->db->query("UPDATE `" . DB_PREFIX . "ocs_store` SET " . $set . " WHERE store_id = '" . (int)$store_id . "'");
		} else {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "ocs_store` SET " . $set);

			$store_id = $this->db->getLastId();
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "ocs_store_description` WHERE store_id = '" . (int)$store_id . "'");

		foreach ($d['desc'] as $language_id => $row) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "ocs_store_description` SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($row['name']) . "', address = '" . $this->db->escape($row['address']) . "', hours = '" . $this->db->escape($row['hours']) . "'");
		}

		$this->cache->delete('ocs_city');

		return $store_id;
	}

	public function deleteStore($store_id) {
		foreach (array('ocs_store', 'ocs_store_description', 'ocs_store_stock') as $table) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . $table . "` WHERE store_id = '" . (int)$store_id . "'");
		}

		$this->cache->delete('ocs_city');
	}
}
