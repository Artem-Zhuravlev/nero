<?php
// Extension: ocs (theme). Top menu of the header (SPEC-050 REQ-TOP-001, 002, 020): sanitising of the theme setting and the template data. Pure, no registry access.
// Output is escaped here because Twig autoescape is off.

namespace Ocs;

class TopMenu {
	const MAX_PAGES = 12;

	private static function cut($text, $max) {
		$text = trim(htmlspecialchars_decode((string)$text, ENT_QUOTES));

		return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
	}

	/**
	 * @param mixed $raw posted theme_ocs_top_menu: enabled, contacts, blog, sale, title[code], pages[id][on|sort]
	 * @param array $codes language codes whose group titles are kept
	 * @param array $validIds ids of existing information pages
	 * @return array enabled, contacts, blog, sale (0/1), title => [code => text], pages => [ids in display order]
	 */
	public static function sanitize($raw, array $codes, array $validIds) {
		$raw = is_array($raw) ? $raw : array();
		$out = array(
			'enabled'  => !empty($raw['enabled']) ? 1 : 0,
			'contacts' => !empty($raw['contacts']) ? 1 : 0,
			'blog'     => !empty($raw['blog']) ? 1 : 0,
			'sale'     => !empty($raw['sale']) ? 1 : 0,
			'title'    => array(),
			'pages'    => array()
		);

		foreach ($codes as $code) {
			$out['title'][$code] = self::cut(isset($raw['title'][$code]) ? $raw['title'][$code] : '', 60);
		}

		$valid = array_flip(array_map('intval', $validIds));
		$picked = array();

		foreach (isset($raw['pages']) && is_array($raw['pages']) ? $raw['pages'] : array() as $id => $row) {
			$id = (int)$id;

			if ($id > 0 && isset($valid[$id]) && is_array($row) && !empty($row['on'])) {
				$picked[$id] = isset($row['sort']) ? (int)$row['sort'] : 0;
			}
		}

		asort($picked);

		$out['pages'] = array_slice(array_keys($picked), 0, self::MAX_PAGES);

		return $out;
	}

	/**
	 * Template data, or null when there is nothing to show.
	 *
	 * @param mixed $cfg the saved setting
	 * @param string $code current language code
	 * @param string $default store default language code
	 * @param string $fallback group title used when none is set for the languages
	 * @param array $titles information_id => title (current language)
	 * @param array $links contacts / blog / sale => [href, title] (blog may be missing), pages => [information_id => href]
	 * @param string $active "information:<id>", "contact", "blog", "sale" or ''
	 */
	public static function view($cfg, $code, $default, $fallback, array $titles, array $links, $active) {
		if (!is_array($cfg) || empty($cfg['enabled'])) {
			return null;
		}

		$item = function ($key) use ($cfg, $links, $active) {
			if (empty($cfg[$key]) || empty($links[$key]['href'])) {
				return null;
			}

			return array('title' => Contacts::e($links[$key]['title']), 'href' => Contacts::url($links[$key]['href']), 'active' => $active === $key);
		};

		$pages = array();

		foreach (isset($cfg['pages']) && is_array($cfg['pages']) ? $cfg['pages'] : array() as $id) {
			if (isset($titles[$id]) && !empty($links['pages'][$id])) {
				$pages[] = array('title' => Contacts::e($titles[$id]), 'href' => Contacts::url($links['pages'][$id]), 'active' => $active === 'information:' . (int)$id);
			}
		}

		$contacts = $item('contacts');
		$blog = $item('blog');
		$sale = $item('sale');

		if (!$pages && !$contacts && !$blog && !$sale) {
			return null;
		}

		$title = '';

		foreach (array($code, $default) as $c) {
			if (isset($cfg['title'][$c]) && trim((string)$cfg['title'][$c]) !== '') {
				$title = (string)$cfg['title'][$c];

				break;
			}
		}

		return array('title' => Contacts::e($title !== '' ? $title : $fallback), 'pages' => $pages, 'contacts' => $contacts, 'blog' => $blog, 'sale' => $sale);
	}
}
