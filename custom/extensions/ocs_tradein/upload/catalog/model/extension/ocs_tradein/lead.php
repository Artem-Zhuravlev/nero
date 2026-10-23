<?php
// Extension: ocs_tradein. Leads and the category match of the banner. Owner: SPEC-036.

class ModelExtensionOcsTradeinLead extends Model {
	public function add($product_id, $clean, $photos, $ip) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "ocs_tradein_lead` SET product_id = '" . (int)$product_id . "', language_id = '" . (int)$this->config->get('config_language_id') . "', name = '" . $this->db->escape($clean['name']) . "', phone = '" . $this->db->escape($clean['phone']) . "', email = '" . $this->db->escape($clean['email']) . "', device = '" . $this->db->escape($clean['comment']) . "', photos = '" . $this->db->escape(implode(',', $photos)) . "', status = '0', ip = '" . $this->db->escape(substr($ip, 0, 40)) . "', date_added = NOW()");

		return $this->db->getLastId();
	}

	/**
	 * Does the product belong to one of the banner categories (or to a subcategory of them)?
	 *
	 * @param int[] $set configured category ids
	 */
	public function productInCategories($product_id, $set) {
		if (!$set) {
			return false;
		}

		return (bool)$this->db->query("SELECT 1 FROM `" . DB_PREFIX . "product_to_category` p2c INNER JOIN `" . DB_PREFIX . "category_path` cp ON (cp.category_id = p2c.category_id) WHERE p2c.product_id = '" . (int)$product_id . "' AND cp.path_id IN (" . implode(',', array_map('intval', $set)) . ") LIMIT 1")->num_rows;
	}

	public function categoryInCategories($category_id, $set) {
		if (!$set || !$category_id) {
			return false;
		}

		return (bool)$this->db->query("SELECT 1 FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" . (int)$category_id . "' AND path_id IN (" . implode(',', array_map('intval', $set)) . ") LIMIT 1")->num_rows;
	}
}
