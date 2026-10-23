<?php
// Extension: ocs_stickers. Admin data access: stickers, their names and manual assignments. Owner: SPEC-029 (REQ-STK-001..003).
// Every write clears the storefront cache of the definitions (and the facts cache is not used: facts are read live).

class ModelExtensionOcsStickersSticker extends Model {
	public function getStickers() {
		return $this->db->query("SELECT s.*, (SELECT sd.name FROM " . DB_PREFIX . "ocs_sticker_description sd WHERE sd.sticker_id = s.sticker_id AND sd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS name, (SELECT COUNT(*) FROM " . DB_PREFIX . "ocs_sticker_product sp WHERE sp.sticker_id = s.sticker_id) AS assigned FROM " . DB_PREFIX . "ocs_sticker s ORDER BY s.priority DESC, s.sticker_id")->rows;
	}

	public function getSticker($sticker_id) {
		$row = $this->db->query("SELECT * FROM " . DB_PREFIX . "ocs_sticker WHERE sticker_id = '" . (int)$sticker_id . "'")->row;

		if ($row) {
			$row['names'] = array();

			foreach ($this->db->query("SELECT language_id, name FROM " . DB_PREFIX . "ocs_sticker_description WHERE sticker_id = '" . (int)$sticker_id . "'")->rows as $d) {
				$row['names'][(int)$d['language_id']] = $d['name'];
			}
		}

		return $row;
	}

	/**
	 * @param array $data names (language_id => text), text_color, bg_color, position, priority, rule_type, rule_value, show_pct, category_id, manufacturer_id, status
	 * @return int sticker_id
	 */
	public function save($sticker_id, $data) {
		$set = "text_color = '" . $this->db->escape($data['text_color']) . "', bg_color = '" . $this->db->escape($data['bg_color']) . "', position = '" . $this->db->escape($data['position']) . "', priority = '" . (int)$data['priority'] . "', rule_type = '" . $this->db->escape($data['rule_type']) . "', rule_value = '" . (int)$data['rule_value'] . "', show_pct = '" . (int)!empty($data['show_pct']) . "', category_id = '" . (int)$data['category_id'] . "', manufacturer_id = '" . (int)$data['manufacturer_id'] . "', status = '" . (int)!empty($data['status']) . "'";

		if ($sticker_id) {
			$this->db->query("UPDATE " . DB_PREFIX . "ocs_sticker SET " . $set . " WHERE sticker_id = '" . (int)$sticker_id . "'");
		} else {
			$this->db->query("INSERT INTO " . DB_PREFIX . "ocs_sticker SET code = '', " . $set);

			$sticker_id = $this->db->getLastId();
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "ocs_sticker_description WHERE sticker_id = '" . (int)$sticker_id . "'");

		foreach ($data['names'] as $language_id => $name) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "ocs_sticker_description SET sticker_id = '" . (int)$sticker_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape(utf8_substr($name, 0, 32)) . "'");
		}

		$this->cache->delete('ocs_stickers');

		return (int)$sticker_id;
	}

	public function delete($sticker_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "ocs_sticker WHERE sticker_id = '" . (int)$sticker_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "ocs_sticker_description WHERE sticker_id = '" . (int)$sticker_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "ocs_sticker_product WHERE sticker_id = '" . (int)$sticker_id . "'");

		$this->cache->delete('ocs_stickers');
	}

	/**
	 * @return int[] product ids assigned to a sticker
	 */
	public function getAssigned($sticker_id) {
		$ids = array();

		foreach ($this->db->query("SELECT product_id FROM " . DB_PREFIX . "ocs_sticker_product WHERE sticker_id = '" . (int)$sticker_id . "' ORDER BY product_id")->rows as $row) {
			$ids[] = (int)$row['product_id'];
		}

		return $ids;
	}

	/**
	 * Bulk assignment: replaces the product list of a sticker (ids that are not products are ignored).
	 *
	 * @param int[] $product_ids
	 */
	public function setAssigned($sticker_id, $product_ids) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "ocs_sticker_product WHERE sticker_id = '" . (int)$sticker_id . "'");

		$ids = array_unique(array_filter(array_map('intval', (array)$product_ids)));

		if ($ids) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "ocs_sticker_product (sticker_id, product_id) SELECT '" . (int)$sticker_id . "', product_id FROM " . DB_PREFIX . "product WHERE product_id IN (" . implode(',', $ids) . ")");
		}
	}

	/**
	 * @return int[] sticker ids assigned to a product
	 */
	public function getProductStickers($product_id) {
		$ids = array();

		foreach ($this->db->query("SELECT sticker_id FROM " . DB_PREFIX . "ocs_sticker_product WHERE product_id = '" . (int)$product_id . "'")->rows as $row) {
			$ids[] = (int)$row['sticker_id'];
		}

		return $ids;
	}

	/**
	 * Product form: replaces the stickers of one product.
	 *
	 * @param int[] $sticker_ids
	 */
	public function setProductStickers($product_id, $sticker_ids) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "ocs_sticker_product WHERE product_id = '" . (int)$product_id . "'");

		foreach (array_unique(array_filter(array_map('intval', (array)$sticker_ids))) as $id) {
			$this->db->query("INSERT IGNORE INTO " . DB_PREFIX . "ocs_sticker_product (sticker_id, product_id) SELECT sticker_id, '" . (int)$product_id . "' FROM " . DB_PREFIX . "ocs_sticker WHERE sticker_id = '" . (int)$id . "'");
		}
	}
}
