<?php
// Extension: ocs_shell. Pure helpers that turn shell settings into template data. Owner: SPEC-031.
// No registry access: everything comes in as arguments so it is unit-testable (SPEC-021).

namespace Ocs;

class Shell {
	/** Allowed URL schemes for links coming from settings (REQ-ARCH-063: output is not auto-escaped). */
	private static $schemes = array('http', 'https', 'tel', 'mailto', 'tg', 'viber');

	/**
	 * Escapes a value for HTML output. Admin POST data reaches the settings already entity-encoded
	 * (core Request::clean), so it is decoded first: "A &amp; B" and "A & B" both print as "A &amp; B".
	 */
	public static function e($value) {
		return htmlspecialchars(htmlspecialchars_decode((string)$value, ENT_QUOTES), ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Returns an escaped URL when its scheme is allowed (or it is site-relative), otherwise ''.
	 */
	public static function safeUrl($url) {
		$url = htmlspecialchars_decode(trim((string)$url), ENT_QUOTES);

		if ($url === '') {
			return '';
		}

		if ($url[0] === '/' && (!isset($url[1]) || $url[1] !== '/')) {
			return self::e($url);
		}

		if (preg_match('#^([a-z][a-z0-9+.-]*):#i', $url, $m) && in_array(strtolower($m[1]), self::$schemes, true)) {
			return self::e($url);
		}

		return '';
	}

	/**
	 * Parses lines "Label|url" (url optional) into [['text' => , 'href' => ], ...]. Text is escaped.
	 */
	public static function parseLinks($text) {
		$out = array();

		foreach (preg_split('/\r\n|\r|\n/', (string)$text) as $line) {
			$line = trim($line);

			if ($line === '') {
				continue;
			}

			$parts = explode('|', $line, 2);

			$out[] = array(
				'text' => self::e(trim($parts[0])),
				'href' => isset($parts[1]) ? self::safeUrl($parts[1]) : ''
			);
		}

		return $out;
	}

	/**
	 * Parses footer groups: blocks separated by an empty line, first line is the title, others are "Label|url".
	 */
	public static function parseGroups($text) {
		$out = array();

		// Browsers submit textareas with CRLF: normalise first, otherwise a single CRLF would look like an empty line.
		$text = str_replace(array("\r\n", "\r"), "\n", (string)$text);

		foreach (preg_split('/\n[ \t]*\n/', trim($text)) as $block) {
			$lines = preg_split('/\r\n|\r|\n/', trim($block));
			$title = trim((string)array_shift($lines));

			if ($title === '') {
				continue;
			}

			$out[] = array(
				'title' => self::e($title),
				'links' => self::parseLinks(implode("\n", $lines))
			);
		}

		return $out;
	}

	/**
	 * Phone numbers, one per line; href is a tel: link with digits and a leading plus only.
	 */
	public static function parsePhones($text) {
		$out = array();

		foreach (preg_split('/\r\n|\r|\n/', (string)$text) as $line) {
			$line = trim($line);

			if ($line === '') {
				continue;
			}

			$out[] = array(
				'text' => self::e($line),
				'href' => 'tel:' . preg_replace('/(?!^\+)[^0-9]/', '', $line)
			);
		}

		return $out;
	}

	/**
	 * Announcement for the given language and moment, or null (REQ-SHL-001).
	 *
	 * @param array  $settings keys: l => [code => [announcement_text, announcement_url]], announcement_from, announcement_to (Y-m-d)
	 * @param string $code     language code
	 * @param int    $now      unix time
	 */
	public static function announcement($settings, $code, $now) {
		$l = isset($settings['l'][$code]) && is_array($settings['l'][$code]) ? $settings['l'][$code] : array();
		$text = isset($l['announcement_text']) ? trim((string)$l['announcement_text']) : '';

		if ($text === '') {
			return null;
		}

		$from = !empty($settings['announcement_from']) ? strtotime($settings['announcement_from'] . ' 00:00:00') : false;
		$to = !empty($settings['announcement_to']) ? strtotime($settings['announcement_to'] . ' 23:59:59') : false;

		if (($from && $now < $from) || ($to && $now > $to)) {
			return null;
		}

		return array(
			'id'   => substr(md5($code . '|' . $text), 0, 10),
			'text' => self::e($text),
			'href' => isset($l['announcement_url']) ? self::safeUrl($l['announcement_url']) : ''
		);
	}

	/**
	 * Messenger buttons from settings keys telegram, viber, messenger (only those with a valid URL).
	 */
	public static function messengers($settings) {
		$names = array('telegram' => 'Telegram', 'viber' => 'Viber', 'messenger' => 'Messenger');
		$out = array();

		foreach ($names as $key => $name) {
			$href = isset($settings[$key]) ? self::safeUrl($settings[$key]) : '';

			if ($href !== '') {
				$out[] = array('key' => $key, 'name' => $name, 'title' => $name, 'icon' => 'i-' . $key, 'href' => $href);
			}
		}

		return $out;
	}
}
