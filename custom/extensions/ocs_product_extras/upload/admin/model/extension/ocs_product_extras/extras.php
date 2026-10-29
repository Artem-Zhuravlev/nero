<?php
// Extension: ocs_product_extras. Admin data access: moderation queue and video. Owner: SPEC-035.

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

	public function getVideoId($product_id) {
		$row = $this->db->query("SELECT video_id FROM `" . DB_PREFIX . "ocs_product_video` WHERE product_id = '" . (int)$product_id . "'")->row;

		return $row ? $row['video_id'] : '';
	}

	/**
	 * Stores the YouTube id of a product ('' removes it). The caller passes an id validated by Ocs\Video::parse().
	 */
	public function setVideo($product_id, $video_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "ocs_product_video` WHERE product_id = '" . (int)$product_id . "'");

		if (\Ocs\Video::valid($video_id)) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "ocs_product_video` SET product_id = '" . (int)$product_id . "', video_id = '" . $this->db->escape($video_id) . "'");
		}
	}

	public function deleteProduct($product_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "ocs_product_video` WHERE product_id = '" . (int)$product_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "ocs_product_accessory` WHERE product_id = '" . (int)$product_id . "' OR accessory_id = '" . (int)$product_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "ocs_question` WHERE product_id = '" . (int)$product_id . "'");
	}
}
