<?php
// Extension: ocs_newsletter. Own subscriber list. Owner: SPEC-033 (REQ-HOM-030).

class ModelExtensionOcsNewsletterSubscriber extends Model {
	public function find($email) {
		return $this->db->query("SELECT * FROM `" . DB_PREFIX . "ocs_subscriber` WHERE email = '" . $this->db->escape($email) . "'")->row;
	}

	/**
	 * Adds the address, or refreshes an unconfirmed one. Returns array(token, confirmed, is_new).
	 */
	public function save($email, $language_id, $confirmed, $ip) {
		$row = $this->find($email);

		if ($row) {
			// a confirmed address stays as it is; an unconfirmed one is confirmed only when no confirmation is required
			if (!$row['confirmed'] && $confirmed) {
				$this->db->query("UPDATE `" . DB_PREFIX . "ocs_subscriber` SET confirmed = '1', language_id = '" . (int)$language_id . "' WHERE subscriber_id = '" . (int)$row['subscriber_id'] . "'");

				return array($row['token'], 1, false);
			}

			return array($row['token'], (int)$row['confirmed'], false);
		}

		$token = \Ocs\Newsletter::token();

		$this->db->query("INSERT INTO `" . DB_PREFIX . "ocs_subscriber` SET email = '" . $this->db->escape($email) . "', language_id = '" . (int)$language_id . "', confirmed = '" . (int)$confirmed . "', token = '" . $this->db->escape($token) . "', ip = '" . $this->db->escape(substr($ip, 0, 40)) . "', date_added = NOW()");

		return array($token, (int)$confirmed, true);
	}

	public function confirm($token) {
		$this->db->query("UPDATE `" . DB_PREFIX . "ocs_subscriber` SET confirmed = '1' WHERE token = '" . $this->db->escape($token) . "'");

		return $this->db->countAffected() > 0;
	}

	public function remove($token) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "ocs_subscriber` WHERE token = '" . $this->db->escape($token) . "'");

		return $this->db->countAffected() > 0;
	}

	public function setCustomerFlag($customer_id) {
		$this->db->query("UPDATE `" . DB_PREFIX . "customer` SET newsletter = '1' WHERE customer_id = '" . (int)$customer_id . "'");
	}
}
