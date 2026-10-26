<?php
// Extension: ocs (theme). Store-wide info blocks of the product page (delivery, returns, payment ...): sanitising for the theme settings and the storefront view.
// Pure, no registry access (unit-testable). Output is escaped here because Twig autoescape is off. Owner: SPEC-048 (REQ-INF-001, 002).

namespace Ocs;

class InfoBlocks {
	/** admin icon name => sprite symbol id */
	public static $icons = array(
		'truck' => 'i-truck', 'return' => 'i-return', 'card' => 'i-card', 'shield' => 'i-shield',
		'gift' => 'i-gift', 'clock' => 'i-clock', 'phone' => 'i-phone', 'info' => 'i-info'
	);

	const MAX = 10;

	private static function cut($text, $max) {
		$text = trim(htmlspecialchars_decode((string)$text, ENT_QUOTES));

		return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
	}

	/**
	 * Cleans the rows posted by the admin editor. A row needs a title or a text in at least one language; the icon must be on the list;
	 * the link is optional and must have an allowed scheme.
	 *
	 * @return array list of [icon, title => [code => text], text => [code => text], url, sort]
	 */
	public static function sanitize($rows, array $codes) {
		$out = array();

		if (!is_array($rows)) {
			return $out;
		}

		foreach ($rows as $row) {
			if (!is_array($row) || !isset($row['icon']) || !isset(self::$icons[$row['icon']])) {
				continue;
			}

			$title = array();
			$text = array();
			$any = false;

			foreach ($codes as $code) {
				$title[$code] = self::cut(isset($row['title'][$code]) ? $row['title'][$code] : '', 80);
				$text[$code] = self::cut(isset($row['text'][$code]) ? $row['text'][$code] : '', 500);
				$any = $any || $title[$code] !== '' || $text[$code] !== '';
			}

			if (!$any) {
				continue;
			}

			$url = isset($row['url']) ? htmlspecialchars_decode(trim((string)$row['url']), ENT_QUOTES) : '';

			$out[] = array(
				'icon'  => $row['icon'],
				'title' => $title,
				'text'  => $text,
				'url'   => Contacts::url($url) === '' ? '' : $url,
				'sort'  => isset($row['sort']) ? (int)$row['sort'] : 0
			);
		}

		usort($out, function ($a, $b) {
			return $a['sort'] - $b['sort'];
		});

		return array_slice($out, 0, self::MAX);
	}

	/**
	 * Template data for one language (fallback: the default language), every text escaped, line breaks as <br>.
	 */
	public static function view($rows, $code, $default) {
		$out = array();

		foreach (is_array($rows) ? $rows : array() as $row) {
			if (!is_array($row) || !isset($row['icon'], self::$icons[$row['icon']])) {
				continue;
			}

			$pick = function ($field) use ($row, $code, $default) {
				foreach (array($code, $default) as $c) {
					if (isset($row[$field][$c]) && trim((string)$row[$field][$c]) !== '') {
						return (string)$row[$field][$c];
					}
				}

				return '';
			};

			$title = $pick('title');
			$text = $pick('text');

			if ($title === '' && $text === '') {
				continue;
			}

			$out[] = array(
				'icon'  => self::$icons[$row['icon']],
				'title' => Contacts::e($title),
				'text'  => str_replace(array("\r\n", "\r", "\n"), '<br>', Contacts::e($text)),
				'href'  => Contacts::url(isset($row['url']) ? $row['url'] : '')
			);
		}

		return $out;
	}
}
