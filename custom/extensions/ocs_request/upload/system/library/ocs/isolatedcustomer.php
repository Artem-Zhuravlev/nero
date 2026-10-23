<?php
// Extension: ocs_request. Guest stand-in for a private Cart\Cart instance (id 0: no cart migration of a logged-in customer). Owner: SPEC-025.

namespace Ocs;

class IsolatedCustomer {
	public function getId() {
		return 0;
	}
}
