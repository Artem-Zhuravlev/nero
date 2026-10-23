<?php
// Extension: ocs_request. Throw-away session for a private Cart\Cart instance. Owner: SPEC-025.
// Cart\Cart reads only getId() and ->data of the session, so quick orders never touch the visitor's own cart.

namespace Ocs;

class IsolatedSession {
	public $data = array();

	private $id;

	public function __construct() {
		$this->id = 'ocs' . bin2hex(random_bytes(14));
	}

	public function getId() {
		return $this->id;
	}
}
