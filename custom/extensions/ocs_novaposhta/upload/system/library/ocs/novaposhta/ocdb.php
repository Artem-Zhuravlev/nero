<?php
// Extension: ocs_novaposhta. Adapter from core's DB object to the Ocs\Novaposhta\Db port. Owner: SPEC-085.

namespace Ocs\Novaposhta;

class OcDb implements Db {
	private $db;

	public function __construct($db) {
		$this->db = $db;
	}

	public function rows($sql) {
		$result = $this->db->query($sql);

		return isset($result->rows) ? $result->rows : array();
	}

	public function exec($sql) {
		$this->db->query($sql);

		return (int)$this->db->countAffected();
	}

	public function escape($value) {
		return $this->db->escape((string)$value);
	}

	public function lock($name) {
		$rows = $this->rows("SELECT GET_LOCK('" . $this->escape($name) . "', 0) AS l");

		return !empty($rows[0]['l']);
	}

	public function unlock($name) {
		$this->db->query("SELECT RELEASE_LOCK('" . $this->escape($name) . "')");
	}
}
