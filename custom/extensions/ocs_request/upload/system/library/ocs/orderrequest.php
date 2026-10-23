<?php
// Extension: ocs_request. Pure helpers shared by quick order, pre-order and back-in-stock forms. Owner: SPEC-025.
// No registry access: unit-testable (SPEC-021).

namespace Ocs;

class OrderRequest {
	/**
	 * Escapes a setting value for HTML output; admin POST data is stored entity-encoded by core, so it is decoded first.
	 */
	public static function e($value) {
		return htmlspecialchars(htmlspecialchars_decode((string)$value, ENT_QUOTES), ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Parses lines "Label|URL" (URL optional) of a setting into array(array('text' => plain label, 'url' => safe URL or '')).
	 * Plain text (entities decoded) for JSON output; only http(s), tel, mailto and site-relative URLs survive.
	 */
	public static function parseLines($text) {
		$out = array();

		foreach (preg_split('/\r\n|\r|\n/', (string)$text) as $line) {
			$parts = explode('|', htmlspecialchars_decode(trim($line), ENT_QUOTES), 2);
			$label = trim($parts[0]);

			if ($label === '') {
				continue;
			}

			$url = isset($parts[1]) ? trim($parts[1]) : '';

			if ($url !== '' && !preg_match('#^(https?://|tel:|mailto:|/(?!/))#i', $url)) {
				$url = '';
			}

			$out[] = array('text' => $label, 'url' => $url);
		}

		return $out;
	}

	/**
	 * Validates a contact form.
	 *
	 * @param array $post   raw POST
	 * @param array $fields field => array('enabled' => bool, 'required' => bool) for name, phone, email, comment
	 * @return array array('errors' => field => code, 'clean' => field => value)
	 */
	public static function validate($post, $fields) {
		$errors = array();
		$clean = array();

		// honeypot: bots fill the hidden "website" field
		if (!empty($post['website'])) {
			return array('errors' => array('spam' => 'spam'), 'clean' => array());
		}

		foreach (array('name', 'phone', 'email', 'comment') as $field) {
			$cfg = isset($fields[$field]) ? $fields[$field] : array('enabled' => false, 'required' => false);

			if (empty($cfg['enabled'])) {
				$clean[$field] = '';

				continue;
			}

			$value = isset($post[$field]) ? trim(preg_replace('/\s+/u', ' ', (string)$post[$field])) : '';

			if ($field === 'comment') {
				$value = isset($post[$field]) ? trim((string)$post[$field]) : '';
			}

			if ($value === '') {
				if (!empty($cfg['required'])) {
					$errors[$field] = 'required';
				}

				$clean[$field] = '';

				continue;
			}

			if ($field === 'name' && (mb_strlen($value, 'UTF-8') < 2 || mb_strlen($value, 'UTF-8') > 64)) {
				$errors[$field] = 'length';
			} elseif ($field === 'phone') {
				$value = self::normalizePhone($value);

				if ($value === '') {
					$errors[$field] = 'format';
				}
			} elseif ($field === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
				$errors[$field] = 'format';
			} elseif ($field === 'comment' && mb_strlen($value, 'UTF-8') > 1000) {
				$errors[$field] = 'length';
			}

			$clean[$field] = $value;
		}

		return array('errors' => $errors, 'clean' => $clean);
	}

	/**
	 * Keeps digits and a leading plus; 9-15 digits are accepted, otherwise ''.
	 */
	public static function normalizePhone($value) {
		$plus = strpos(trim((string)$value), '+') === 0 ? '+' : '';
		$digits = preg_replace('/\D+/', '', (string)$value);

		if (strlen($digits) < 9 || strlen($digits) > 15) {
			return '';
		}

		return $plus . $digits;
	}

	/**
	 * Sliding-window rate limit over unix timestamps.
	 *
	 * @param int[] $stamps previous request times of one client
	 * @return array array(bool allowed, int[] stamps to store)
	 */
	public static function rateLimit($stamps, $now, $max = 5, $window = 60) {
		$keep = array();

		foreach ((array)$stamps as $t) {
			if ((int)$t > $now - $window) {
				$keep[] = (int)$t;
			}
		}

		if (count($keep) >= $max) {
			return array(false, $keep);
		}

		$keep[] = (int)$now;

		return array(true, $keep);
	}

	/**
	 * Parses "product_id|mode|YYYY-MM-DD" lines (mode on/off, date optional) into product_id => array(mode, date).
	 */
	public static function parseOverrides($text) {
		$out = array();

		foreach (preg_split('/\r\n|\r|\n/', (string)$text) as $line) {
			$parts = array_map('trim', explode('|', $line));

			if (count($parts) < 2 || !ctype_digit($parts[0]) || !in_array(strtolower($parts[1]), array('on', 'off'), true)) {
				continue;
			}

			$date = isset($parts[2]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $parts[2]) ? $parts[2] : '';

			$out[(int)$parts[0]] = array('mode' => strtolower($parts[1]), 'date' => $date);
		}

		return $out;
	}

	/**
	 * Whether the pre-order button applies to a product.
	 *
	 * @param bool  $global      global switch
	 * @param int   $quantity    stock quantity
	 * @param array $override    array('mode' => on|off) or empty
	 */
	public static function preorderApplies($global, $quantity, $override) {
		if (!empty($override['mode'])) {
			if ($override['mode'] === 'off') {
				return false;
			}

			return (int)$quantity <= 0;
		}

		return (bool)$global && (int)$quantity <= 0;
	}
}
