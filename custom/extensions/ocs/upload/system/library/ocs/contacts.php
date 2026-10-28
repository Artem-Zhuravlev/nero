<?php
// Extension: ocs (theme). Shop contacts of the footer / header: address, hours, phones (per language) and a list of messenger / social links with an icon.
// Pure, no registry access (unit-testable). Output is escaped here because Twig autoescape is off. Owner: SPEC-047 (REQ-FTR-002, REQ-SET-001..003, REQ-SET-020).

namespace Ocs;

class Contacts {
	/** admin icon name => sprite symbol id; the order is the order of the admin select */
	public static $icons = array(
		'telegram' => 'i-telegram', 'viber' => 'i-viber', 'whatsapp' => 'i-whatsapp', 'messenger' => 'i-messenger',
		'instagram' => 'i-instagram', 'facebook' => 'i-facebook', 'youtube' => 'i-youtube', 'tiktok' => 'i-tiktok',
		'x' => 'i-twitter', 'linkedin' => 'i-linkedin', 'email' => 'i-mail', 'phone' => 'i-phone', 'map' => 'i-pin', 'site' => 'i-globe'
	);

	public static $kinds = array('messenger', 'social');

	private static $schemes = array('http', 'https', 'tel', 'mailto', 'tg', 'viber', 'whatsapp');

	const MAX_LINKS = 20;

	public static function e($value) {
		return htmlspecialchars(htmlspecialchars_decode((string)$value, ENT_QUOTES), ENT_QUOTES, 'UTF-8');
	}

	/**
	 * @return string escaped URL with an allowed scheme (or site-relative), '' otherwise
	 */
	public static function url($url) {
		$url = htmlspecialchars_decode(trim((string)$url), ENT_QUOTES);

		if ($url === '' || strlen($url) > 500 || preg_match('/[\x00-\x1f\s]/', $url)) {
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
	 * Phones, one per line: "+380 44 123 45 67" or "+380441234567 | Sales". The href keeps digits and a leading plus only.
	 *
	 * @return array of [text, label, href]; lines without digits are dropped
	 */
	public static function phones($text) {
		$out = array();

		foreach (preg_split('/\r\n|\r|\n/', htmlspecialchars_decode((string)$text, ENT_QUOTES)) as $line) {
			$parts = explode('|', $line, 2);
			$number = trim($parts[0]);
			$digits = preg_replace('/(?!^\+)[^0-9]/', '', $number);

			if (strlen(preg_replace('/\D/', '', $digits)) < 3) {
				continue;
			}

			$out[] = array(
				'text'  => self::e($number),
				'label' => isset($parts[1]) ? self::e(trim($parts[1])) : '',
				'href'  => 'tel:' . $digits
			);
		}

		return array_slice($out, 0, 10);
	}

	/**
	 * Cleans the rows posted by the admin editor: allowed icon, kind, titles per language code, a valid URL, sort order. Invalid rows are dropped.
	 *
	 * @param mixed $rows posted rows (any keys)
	 * @param array $codes language codes whose titles are kept
	 * @return array list of [icon, kind, title => [code => text], url, sort]
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

			$url = isset($row['url']) ? htmlspecialchars_decode(trim((string)$row['url']), ENT_QUOTES) : '';

			// "t.me/shop" and "www.site.com" without a scheme are taken as https
			if ($url !== '' && $url[0] !== '/' && !preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) && preg_match('#^[^\s/@]+\.[^\s/@]+#', $url)) {
				$url = 'https://' . $url;
			}

			if (self::url($url) === '') {
				continue;
			}

			$titles = array();

			foreach ($codes as $code) {
				$t = isset($row['title'][$code]) ? trim(htmlspecialchars_decode((string)$row['title'][$code], ENT_QUOTES)) : '';
				$titles[$code] = function_exists('mb_substr') ? mb_substr($t, 0, 60, 'UTF-8') : substr($t, 0, 60);
			}

			$out[] = array(
				'icon'  => $row['icon'],
				'kind'  => isset($row['kind']) && in_array($row['kind'], self::$kinds, true) ? $row['kind'] : 'social',
				'title' => $titles,
				'url'   => $url,
				'sort'  => isset($row['sort']) ? (int)$row['sort'] : 0
			);
		}

		usort($out, function ($a, $b) {
			return $a['sort'] - $b['sort'];
		});

		return array_slice($out, 0, self::MAX_LINKS);
	}

	/**
	 * Template data for one language.
	 *
	 * @param array $contact theme_ocs_contact: [code => [address, hours, phones]]
	 * @param array $links theme_ocs_contact_links (see sanitize)
	 * @param string $code current language code
	 * @param string $default store default language code (fallback for empty values)
	 * @return array address, hours, phones, links (icon symbol, kind, title, href), messengers, social; every text escaped
	 */
	public static function view($contact, $links, $code, $default) {
		$contact = is_array($contact) ? $contact : array();
		$pick = function ($field) use ($contact, $code, $default) {
			foreach (array($code, $default) as $c) {
				if (isset($contact[$c][$field]) && trim((string)$contact[$c][$field]) !== '') {
					return (string)$contact[$c][$field];
				}
			}

			return '';
		};

		$list = array();

		foreach (is_array($links) ? $links : array() as $row) {
			if (!is_array($row) || !isset($row['icon'], self::$icons[$row['icon']])) {
				continue;
			}

			$href = self::url(isset($row['url']) ? $row['url'] : '');

			if ($href === '') {
				continue;
			}

			$title = '';

			foreach (array($code, $default) as $c) {
				if (isset($row['title'][$c]) && trim((string)$row['title'][$c]) !== '') {
					$title = $row['title'][$c];

					break;
				}
			}

			$list[] = array(
				'icon'  => self::$icons[$row['icon']],
				'key'   => $row['icon'],
				'kind'  => isset($row['kind']) && $row['kind'] === 'messenger' ? 'messenger' : 'social',
				'title' => self::e($title !== '' ? $title : ucfirst($row['icon'])),
				'href'  => $href
			);
		}

		return array(
			'address'    => self::e($pick('address')),
			'hours'      => str_replace(array("\r\n", "\r", "\n"), '<br>', self::e($pick('hours'))),
			'phones'     => self::phones($pick('phones')),
			'links'      => $list,
			'messengers' => array_values(array_filter($list, function ($l) {
				return $l['kind'] === 'messenger';
			})),
			'social'     => array_values(array_filter($list, function ($l) {
				return $l['kind'] === 'social';
			}))
		);
	}
}
