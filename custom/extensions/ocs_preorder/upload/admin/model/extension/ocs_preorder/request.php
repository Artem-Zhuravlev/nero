<?php
// Extension: ocs_preorder. Admin access to pre-order requests. Owner: SPEC-025 (REQ-QOR-012).

class ModelExtensionOcsPreorderRequest extends Model {
	public function getRequests($start = 0, $limit = 50) {
		return $this->db->query("SELECT * FROM " . DB_PREFIX . "ocs_preorder ORDER BY preorder_id DESC LIMIT " . max(0, (int)$start) . "," . max(1, (int)$limit))->rows;
	}

	public function getTotal() {
		return (int)$this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "ocs_preorder")->row['total'];
	}

	public function setStatus($preorder_id, $status) {
		$this->db->query("UPDATE " . DB_PREFIX . "ocs_preorder SET status = '" . (in_array((int)$status, array(0, 1, 2), true) ? (int)$status : 0) . "' WHERE preorder_id = '" . (int)$preorder_id . "'");
	}

	public function delete($preorder_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "ocs_preorder WHERE preorder_id = '" . (int)$preorder_id . "'");
	}
}
