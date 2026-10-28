<?php
// Extension: ocs_cookie. Pure helper that turns the cookie popup settings into template data. Owner: SPEC-083.
// No registry access: everything comes in as arguments so it is unit-testable (SPEC-021). Escaping and URL checks come from \Ocs\Shell.

namespace Ocs;

class Cookie {
	private static $positions = array('card', 'bar');

	/**
	 * Popup data for the given language, or null when there is nothing to show (REQ-CKP-003).
	 *
	 * @param array  $settings keys: l => [code => [title, text, link_label, link_url, accept, necessary]], position, version, days
	 * @param string $code     language directory code
	 */
	public static function build($settings, $code) {
		$l = isset($settings['l'][$code]) && is_array($settings['l'][$code]) ? $settings['l'][$code] : array();
		$text = isset($l['text']) ? trim((string)$l['text']) : '';

		if ($text === '') {
			return null;
		}

		$field = function ($name) use ($l) {
			return isset($l[$name]) ? trim((string)$l[$name]) : '';
		};

		$link_label = $field('link_label');
		$link_href = isset($l['link_url']) ? Shell::safeUrl($l['link_url']) : '';
		$accept = $field('accept');
		$necessary = $field('necessary');

		return array(
			'title'      => Shell::e($field('title')),
			'text'       => Shell::e($text),
			'link_label' => Shell::e($link_label),
			'link_href'  => $link_label !== '' ? $link_href : '',
			'accept'     => Shell::e($accept !== '' ? $accept : 'OK'),
			'necessary'  => Shell::e($necessary),
			'position'   => isset($settings['position']) && in_array($settings['position'], self::$positions, true) ? $settings['position'] : 'card',
			'version'    => max(1, (int)(isset($settings['version']) ? $settings['version'] : 1)),
			'days'       => min(730, max(1, (int)(isset($settings['days']) ? $settings['days'] : 365)))
		);
	}
}
