<?php
// Extension: ocs_newsletter. Admin access to the subscriber list. Owner: SPEC-033 (REQ-HOM-030).

class ModelExtensionOcsNewsletterSubscriber extends Model {
	public function getSubscribers($start, $limit) {
		return $this->db->query("SELECT * FROM `" . DB_PREFIX . "ocs_subscriber` ORDER BY subscriber_id DESC LIMIT " . (int)$start . "," . (int)$limit)->rows;
	}

	public function getTotal() {
		return (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "ocs_subscriber`")->row['total'];
	}

	public function getConfirmed() {
		return $this->db->query("SELECT email, language_id, date_added FROM `" . DB_PREFIX . "ocs_subscriber` WHERE confirmed = '1' ORDER BY subscriber_id ASC")->rows;
	}

	public function delete($subscriber_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "ocs_subscriber` WHERE subscriber_id = '" . (int)$subscriber_id . "'");
	}
}
