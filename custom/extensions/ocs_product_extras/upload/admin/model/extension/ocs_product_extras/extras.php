<?php
// Extension: ocs_product_extras. Admin data access: moderation queue and accessory links. Owner: SPEC-035.

class ModelExtensionOcsProductExtrasExtras extends Model {
	public function getQuestions($start, $limit) {
		return $this->db->query("SELECT q.*, pd.name AS product FROM `" . DB_PREFIX . "ocs_question` q LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (pd.product_id = q.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "') ORDER BY q.status ASC, q.question_id DESC LIMIT " . (int)$start . "," . (int)$limit)->rows;
	}

	public function getTotalQuestions() {
		return (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "ocs_question`")->row['total'];
	}

	public function getPending() {
		return (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "ocs_question` WHERE status = '0'")->row['total'];
	}

	/**
	 * Saves the answer and publishes the question (an answered question is approved by the moderator's action).
	 */
	public function answer($question_id, $answer, $status) {
		$this->db->query("UPDATE `" . DB_PREFIX . "ocs_question` SET answer = '" . $this->db->escape($answer) . "', status = '" . ($status ? 1 : 0) . "' WHERE question_id = '" . (int)$question_id . "'");
	}

	public function deleteQuestion($question_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "ocs_question` WHERE question_id = '" . (int)$question_id . "'");
	}

	public function getAccessoryIds($product_id) {
		$ids = array();

		foreach ($this->db->query("SELECT accessory_id FROM `" . DB_PREFIX . "ocs_product_accessory` WHERE product_id = '" . (int)$product_id . "' ORDER BY sort_order ASC, accessory_id ASC")->rows as $row) {
			$ids[] = (int)$row['accessory_id'];
		}

		return $ids;
	}

	/**
	 * Replaces the accessories of a product; unknown ids and the product itself are dropped.
	 */
	public function setAccessories($product_id, $ids) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "ocs_product_accessory` WHERE product_id = '" . (int)$product_id . "'");

		$sort = 0;

		foreach ($ids as $id) {
			$id = (int)$id;

			if ($id < 1 || $id === (int)$product_id) {
				continue;
			}

			if ($this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product` WHERE product_id = '" . $id . "'")->num_rows) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "ocs_product_accessory` SET product_id = '" . (int)$product_id . "', accessory_id = '" . $id . "', sort_order = '" . $sort++ . "'");
			}
		}
	}

	public function deleteProduct($product_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "ocs_product_accessory` WHERE product_id = '" . (int)$product_id . "' OR accessory_id = '" . (int)$product_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "ocs_question` WHERE product_id = '" . (int)$product_id . "'");
	}

	public function getProductNames($ids) {
		$names = array();

		foreach ($ids as $id) {
			$row = $this->db->query("SELECT name FROM `" . DB_PREFIX . "product_description` WHERE product_id = '" . (int)$id . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'")->row;

			if ($row) {
				$names[(int)$id] = $row['name'];
			}
		}

		return $names;
	}
}
