<?php
// Extension: ocs_newsletter. Pure helpers of the subscriber list (tested without a database). Owner: SPEC-033 (REQ-HOM-030).

namespace Ocs;

class Newsletter {
	/**
	 * Lower-cased, trimmed e-mail or '' when it is not a valid address.
	 */
	public static function email($value) {
		$value = strtolower(trim((string)$value));

		return (strlen($value) <= 96 && filter_var($value, FILTER_VALIDATE_EMAIL)) ? $value : '';
	}

	/**
	 * Random token for the confirm / unsubscribe links.
	 */
	public static function token() {
		return bin2hex(random_bytes(16));
	}

	public static function validToken($value) {
		return is_string($value) && preg_match('/^[a-f0-9]{32}$/', $value) === 1;
	}

	/**
	 * One CSV line (RFC 4180 quoting). Cells that a spreadsheet would read as a formula get a leading apostrophe.
	 */
	public static function csvRow($cells) {
		$out = array();

		foreach ($cells as $cell) {
			$cell = (string)$cell;

			if ($cell !== '' && strpos('=+-@', $cell[0]) !== false) {
				$cell = "'" . $cell;
			}

			$out[] = '"' . str_replace('"', '""', $cell) . '"';
		}

		return implode(',', $out) . "\r\n";
	}
}
