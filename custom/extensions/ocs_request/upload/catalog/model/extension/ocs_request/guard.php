<?php
// Extension: ocs_request. CSRF token and rate limit for the request forms. Owner: SPEC-025 (REQ-QOR-031).

class ModelExtensionOcsRequestGuard extends Model {
	/**
	 * Per-session form token (embedded into every form).
	 */
	public function token() {
		if (empty($this->session->data['ocs_req_token'])) {
			$this->session->data['ocs_req_token'] = bin2hex(random_bytes(16));
		}

		return $this->session->data['ocs_req_token'];
	}

	public function tokenValid($posted) {
		return !empty($this->session->data['ocs_req_token']) && is_string($posted) && hash_equals($this->session->data['ocs_req_token'], $posted);
	}

	/**
	 * Field modes (off|optional|required) -> validator config of \Ocs\OrderRequest::validate().
	 */
	public function config($modes) {
		$out = array();

		foreach (array('name', 'phone', 'email', 'comment') as $field) {
			$mode = isset($modes[$field]) ? $modes[$field] : 'off';

			$out[$field] = array('enabled' => $mode === 'optional' || $mode === 'required', 'required' => $mode === 'required');
		}

		return $out;
	}

	/**
	 * Fields for the shared form template.
	 *
	 * @param array $modes  field => off|optional|required
	 * @param array $labels field => label
	 */
	public function fields($modes, $labels) {
		$types = array('name' => 'text', 'phone' => 'tel', 'email' => 'email', 'comment' => 'textarea');
		$out = array();

		foreach ($this->config($modes) as $field => $cfg) {
			if ($cfg['enabled']) {
				$out[] = array('name' => $field, 'type' => $types[$field], 'label' => isset($labels[$field]) ? $labels[$field] : $field, 'required' => $cfg['required']);
			}
		}

		return $out;
	}

	/**
	 * Sends a JSON response.
	 */
	public function json($payload) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($payload));
	}

	/**
	 * Sends the JSON answer to the browser and ends the HTTP response, so slow follow-up work (order mails) does not
	 * keep the customer waiting. Falls back to a plain flush where fastcgi_finish_request() is not available.
	 */
	public function finish($payload) {
		$body = json_encode($payload);

		ignore_user_abort(true);

		$this->response->setCompression(0);
		$this->response->addHeader('Content-Type: application/json');
		$this->response->addHeader('Content-Length: ' . strlen($body));
		$this->response->addHeader('Connection: close');
		$this->response->setOutput($body);
		$this->response->output();
		$this->response->setOutput('');

		if (function_exists('fastcgi_finish_request')) {
			fastcgi_finish_request();
		} else {
			while (ob_get_level() > 0) {
				ob_end_flush();
			}

			flush();
		}
	}

	/**
	 * 5 submissions per minute per IP (sliding window kept in the cache).
	 *
	 * @return bool true when the request may proceed
	 */
	public function allow($scope) {
		$ip = isset($this->request->server['REMOTE_ADDR']) ? $this->request->server['REMOTE_ADDR'] : '';
		$key = 'ocs_req.' . md5($scope . '|' . $ip);

		$stamps = $this->cache->get($key);

		list($allowed, $stamps) = \Ocs\OrderRequest::rateLimit(is_array($stamps) ? $stamps : array(), time(), 5, 60);

		$this->cache->set($key, $stamps);

		return $allowed;
	}
}
