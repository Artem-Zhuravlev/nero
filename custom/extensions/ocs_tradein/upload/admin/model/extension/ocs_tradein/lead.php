<?php
// Extension: ocs_tradein. Admin access to the leads. Owner: SPEC-036 (REQ-CRD-010).

class ModelExtensionOcsTradeinLead extends Model {
	public function getLeads($start, $limit) {
		return $this->db->query("SELECT * FROM `" . DB_PREFIX . "ocs_tradein_lead` ORDER BY status ASC, lead_id DESC LIMIT " . (int)$start . "," . (int)$limit)->rows;
	}

	public function getTotal() {
		return (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "ocs_tradein_lead`")->row['total'];
	}

	public function getLead($lead_id) {
		return $this->db->query("SELECT * FROM `" . DB_PREFIX . "ocs_tradein_lead` WHERE lead_id = '" . (int)$lead_id . "'")->row;
	}

	public function setStatus($lead_id, $status) {
		$this->db->query("UPDATE `" . DB_PREFIX . "ocs_tradein_lead` SET status = '" . ($status ? 1 : 0) . "' WHERE lead_id = '" . (int)$lead_id . "'");
	}

	public function delete($lead_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "ocs_tradein_lead` WHERE lead_id = '" . (int)$lead_id . "'");
	}

	/**
	 * A stored photo may be served only when some lead lists it.
	 */
	public function photoExists($name) {
		return (bool)$this->db->query("SELECT 1 FROM `" . DB_PREFIX . "ocs_tradein_lead` WHERE FIND_IN_SET('" . $this->db->escape($name) . "', photos) > 0 LIMIT 1")->num_rows;
	}
}
