<?php
// Extension: ocs_novaposhta. Field selectors of the checkout forms the component attaches to. Owner: SPEC-085.
// A group is one address form: city, address (the point picker), zone and country fields. Several selectors per field are comma separated CSS.

namespace Ocs\Novaposhta;

class Presets {
	const FIELDS = array('city', 'address', 'zone', 'country');

	/** @return array name => list of groups */
	public static function all() {
		return array(
			// the theme's own checkout (core one-page checkout markup)
			'default' => array(
				array('city' => '#input-shipping-city', 'address' => '#input-shipping-address-1', 'zone' => '#input-shipping-zone', 'country' => '#input-shipping-country'),
				array('city' => '#input-payment-city', 'address' => '#input-payment-address-1', 'zone' => '#input-payment-zone', 'country' => '#input-payment-country')
			),
			// Simple Checkout: field ids/names by form (register, payment address, shipping address). Verify against the installed version.
			'simple' => array(
				self::simple('shipping_address'),
				self::simple('payment_address'),
				self::simple('register')
			)
		);
	}

	/**
	 * Selector groups for the chosen preset; "custom" uses the admin-entered groups.
	 *
	 * @param string $preset default|simple|custom
	 * @param mixed  $custom decoded setting: list of groups
	 */
	public static function groups($preset, $custom) {
		$all = self::all();

		if ($preset == 'custom') {
			$groups = array();

			foreach (is_array($custom) ? $custom : array() as $group) {
				if (!is_array($group) || empty($group['city']) || empty($group['address'])) {
					continue;
				}

				$clean = array();

				foreach (self::FIELDS as $field) {
					$clean[$field] = isset($group[$field]) ? self::selector($group[$field]) : '';
				}

				$groups[] = $clean;
			}

			return $groups;
		}

		return isset($all[$preset]) ? $all[$preset] : $all['default'];
	}

	/** Parse the admin textarea: one group per line, "city | address | zone | country". */
	public static function parse($text) {
		$groups = array();

		foreach (preg_split('/\R/', (string)$text) as $line) {
			$parts = array_map('trim', explode('|', $line));

			if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
				continue;
			}

			$groups[] = array('city' => $parts[0], 'address' => $parts[1], 'zone' => isset($parts[2]) ? $parts[2] : '', 'country' => isset($parts[3]) ? $parts[3] : '');
		}

		return $groups;
	}

	/** The inverse of parse(), for the admin form. */
	public static function format(array $groups) {
		$lines = array();

		foreach ($groups as $g) {
			$lines[] = implode(' | ', array($g['city'], $g['address'], isset($g['zone']) ? $g['zone'] : '', isset($g['country']) ? $g['country'] : ''));
		}

		return implode("\n", $lines);
	}

	/** Keeps only characters a CSS selector list needs; no markup, quotes allowed for attribute selectors. */
	private static function selector($value) {
		return preg_replace('/[^\w\s\-\.\#\[\]=\,\:\>\*\"\'\(\)]/u', '', (string)$value);
	}

	private static function simple($prefix) {
		return array(
			'city'    => '#' . $prefix . '_city, [name="' . $prefix . '_city"], [name="' . $prefix . '[city]"]',
			'address' => '#' . $prefix . '_address_1, [name="' . $prefix . '_address_1"], [name="' . $prefix . '[address_1]"]',
			'zone'    => '#' . $prefix . '_zone_id, [name="' . $prefix . '_zone_id"], [name="' . $prefix . '[zone_id]"]',
			'country' => '#' . $prefix . '_country_id, [name="' . $prefix . '_country_id"], [name="' . $prefix . '[country_id]"]'
		);
	}
}
