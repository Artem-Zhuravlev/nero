<?php
// Extension: ocs_product_carousel. Pure helpers for the heading markup and the category tabs. Owner: SPEC-039 (REQ-VIS-003).

namespace Ocs;

class ProductCarousel {
	const MODES = array('newest', 'bestsellers', 'discounted');

	/**
	 * Heading text -> safe HTML: everything is escaped, then [[word]] becomes the accent-coloured word.
	 */
	public static function heading($text) {
		$safe = htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');

		return preg_replace('/\[\[(.{1,60}?)\]\]/u', '<span class="ocs-accent">$1</span>', $safe);
	}

	/**
	 * "Label|category_id|mode" lines -> array of array(label, category_id, mode). Wrong lines are skipped; at most 8 tabs.
	 */
	public static function parseTabs($text) {
		$tabs = array();

		foreach (preg_split('/\r\n|\r|\n/', (string)$text) as $line) {
			$parts = array_map('trim', explode('|', $line));

			if (count($parts) < 2 || $parts[0] === '' || mb_strlen($parts[0], 'UTF-8') > 40 || !ctype_digit($parts[1])) {
				continue;
			}

			$mode = isset($parts[2]) && in_array($parts[2], self::MODES, true) ? $parts[2] : 'bestsellers';

			$tabs[] = array('label' => html_entity_decode($parts[0], ENT_QUOTES, 'UTF-8'), 'category_id' => (int)$parts[1], 'mode' => $mode);

			if (count($tabs) >= 8) {
				break;
			}
		}

		return $tabs;
	}
}
