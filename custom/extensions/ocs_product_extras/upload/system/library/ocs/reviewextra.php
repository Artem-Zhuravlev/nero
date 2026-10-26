<?php
// Extension: ocs_product_extras. Pros and cons of a review (SPEC-049 REQ-REV-003): cleaning of the posted text. Pure, no registry access.

namespace Ocs;

class ReviewExtra {
	const MAX = 255;

	/**
	 * @param mixed $value posted text (the core request cleaner has already entity-encoded it)
	 * @return string plain text, tags stripped, single line, at most 255 characters
	 */
	public static function clean($value) {
		if (!is_scalar($value)) {
			return ''; // pros[]=x and similar junk
		}

		$text = html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8');
		$text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));

		return function_exists('mb_substr') ? mb_substr($text, 0, self::MAX, 'UTF-8') : substr($text, 0, self::MAX);
	}
}
