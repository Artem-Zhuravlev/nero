<?php
// Extension: ocs (theme). Default footer links (SPEC-072 REQ-FMN-001, 003, 004): sanitising of the setting and the template groups. Pure, no registry access.
// Output is escaped here because Twig autoescape is off.

namespace Ocs;

class Footer {
	// fixed links: key => footer data key holding the URL, footer language key holding the text
	public static $links = array(
		'contact'      => array('contact', 'text_contact'),
		'return'       => array('return', 'text_return'),
		'sitemap'      => array('sitemap', 'text_sitemap'),
		'manufacturer' => array('manufacturer', 'text_manufacturer'),
		'voucher'      => array('voucher', 'text_voucher'),
		'affiliate'    => array('affiliate', 'text_affiliate'),
		'special'      => array('special', 'text_special'),
		'account'      => array('account', 'text_account'),
		'order'        => array('order', 'text_order'),
		'wishlist'     => array('wishlist', 'text_wishlist'),
		'newsletter'   => array('newsletter', 'text_newsletter')
	);

	// column => fixed link keys; the information column is made of the pages
	public static $columns = array(
		'service' => array('contact', 'return', 'sitemap'),
		'extra'   => array('manufacturer', 'voucher', 'affiliate', 'special', 'account', 'order', 'wishlist', 'newsletter')
	);

	/**
	 * @param array $informationIds ids of existing information pages
	 * @return array every key the setting may mention
	 */
	public static function validKeys(array $informationIds) {
		$keys = array_keys(self::$links);

		foreach ($informationIds as $id) {
			$keys[] = 'info:' . (int)$id;
		}

		return $keys;
	}

	/**
	 * @param mixed $raw posted theme_ocs_footer: show[key] = 1 for every link that stays visible, payments 0/1
	 * @param array $validKeys see validKeys()
	 * @return array hide => [keys], payments => 0|1
	 */
	public static function sanitize($raw, array $validKeys) {
		$raw = is_array($raw) ? $raw : array();
		$show = isset($raw['show']) && is_array($raw['show']) ? $raw['show'] : array();
		$hide = array();

		foreach (array_values(array_unique($validKeys)) as $key) {
			if (empty($show[$key])) {
				$hide[] = $key;
			}
		}

		return array('hide' => $hide, 'payments' => !empty($raw['payments']) ? 1 : 0);
	}

	/**
	 * Saved setting with the defaults of a missing one: nothing hidden, logos shown.
	 */
	public static function normalize($cfg) {
		$hide = is_array($cfg) && isset($cfg['hide']) && is_array($cfg['hide']) ? array_map('strval', $cfg['hide']) : array();
		$payments = is_array($cfg) && array_key_exists('payments', $cfg) ? !empty($cfg['payments']) : true;

		return array('hide' => $hide, 'payments' => $payments);
	}

	/**
	 * @param mixed $cfg the saved setting
	 * @param array $informations rows id, title, href of the pages shown in the footer
	 * @param array $d footer template data: link URLs (contact, return, ...) and texts (text_information, text_service, text_extra, text_contact, ...)
	 * @return array payments => bool, groups => [[key, title, links => [[text, href]]]] without hidden links and empty groups
	 */
	public static function view($cfg, array $informations, array $d) {
		$cfg = self::normalize($cfg);
		$hidden = array_flip($cfg['hide']);
		$text = function ($k) use ($d) {
			return isset($d[$k]) ? Contacts::e($d[$k]) : '';
		};

		$groups = array();
		$items = array();

		foreach ($informations as $row) {
			if (!isset($hidden['info:' . (int)$row['id']])) {
				$items[] = array('text' => Contacts::e($row['title']), 'href' => Contacts::url($row['href']));
			}
		}

		$groups[] = array('key' => 'information', 'title' => $text('text_information'), 'links' => $items);

		foreach (array('service' => 'text_service', 'extra' => 'text_extra') as $column => $title) {
			$items = array();

			foreach (self::$columns[$column] as $key) {
				if (isset($hidden[$key]) || empty($d[self::$links[$key][0]])) {
					continue;
				}

				$items[] = array('text' => $text(self::$links[$key][1]), 'href' => Contacts::url($d[self::$links[$key][0]]));
			}

			$groups[] = array('key' => $column, 'title' => $text($title), 'links' => $items);
		}

		return array('payments' => $cfg['payments'], 'groups' => array_values(array_filter($groups, function ($g) {
			return count($g['links']) > 0;
		})));
	}
}
