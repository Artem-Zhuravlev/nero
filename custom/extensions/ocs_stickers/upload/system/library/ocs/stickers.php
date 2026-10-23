<?php
// Extension: ocs_stickers. Pure sticker rules, priority/limit resolution and colour helpers. Owner: SPEC-029.
// No registry access: unit-testable (SPEC-021).

namespace Ocs;

class Stickers {
	public static $positions = array('tl', 'tr', 'bl');

	public static $rules = array('manual', 'new', 'discount', 'out_of_stock', 'top_sales');

	/**
	 * Normalises #rgb / #rrggbb to lowercase #rrggbb, anything else gives the fallback (colours end up in a style attribute).
	 */
	public static function safeColor($value, $fallback) {
		$value = strtolower(trim((string)$value));

		if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $value, $m)) {
			return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
		}

		return preg_match('/^#[0-9a-f]{6}$/', $value) ? $value : $fallback;
	}

	/**
	 * WCAG contrast ratio of two #rrggbb colours (admin warns below 4.5).
	 */
	public static function contrast($fg, $bg) {
		$l = function ($hex) {
			$c = array();

			foreach (array(1, 3, 5) as $i) {
				$v = hexdec(substr($hex, $i, 2)) / 255;
				$c[] = $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
			}

			return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
		};

		$a = $l(self::safeColor($fg, '#000000'));
		$b = $l(self::safeColor($bg, '#ffffff'));

		return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
	}

	/**
	 * Does a sticker apply to a product?
	 *
	 * Manual assignment always applies. A rule applies only inside its optional category / manufacturer scope.
	 *
	 * @param array $def  sticker_id, rule_type, rule_value, category_id, manufacturer_id
	 * @param array $fact product_id, date_added, quantity, price, special (or null), manufacturer_id, sold, cats[], manual[]
	 */
	public static function matches($def, $fact, $now) {
		if (in_array((int)$def['sticker_id'], array_map('intval', isset($fact['manual']) ? $fact['manual'] : array()), true)) {
			return true;
		}

		if ((int)$def['category_id'] > 0 && !in_array((int)$def['category_id'], array_map('intval', isset($fact['cats']) ? $fact['cats'] : array()), true)) {
			return false;
		}

		if ((int)$def['manufacturer_id'] > 0 && (int)$def['manufacturer_id'] !== (int)(isset($fact['manufacturer_id']) ? $fact['manufacturer_id'] : 0)) {
			return false;
		}

		$value = (int)$def['rule_value'];

		switch ($def['rule_type']) {
			case 'new':
				$added = isset($fact['date_added']) ? strtotime($fact['date_added']) : false;

				return $value > 0 && $added !== false && $added > $now - $value * 86400;
			case 'discount':
				return isset($fact['special']) && $fact['special'] !== null && (float)$fact['price'] > 0 && (float)$fact['special'] < (float)$fact['price'];
			case 'out_of_stock':
				return isset($fact['quantity']) && (int)$fact['quantity'] <= 0;
			case 'top_sales':
				return $value > 0 && isset($fact['sold']) && (int)$fact['sold'] >= $value;
		}

		return false;
	}

	/**
	 * Label of a sticker for a product: "-N%" for discount stickers that show the percentage, otherwise the sticker text.
	 */
	public static function text($def, $fact) {
		if ($def['rule_type'] === 'discount' && !empty($def['show_pct']) && isset($fact['special']) && $fact['special'] !== null && (float)$fact['price'] > 0 && (float)$fact['special'] < (float)$fact['price']) {
			return '-' . (int)round((1 - (float)$fact['special'] / (float)$fact['price']) * 100) . '%';
		}

		return (string)$def['text'];
	}

	/**
	 * Stickers per product and position: higher priority first (then lower id), at most $limit per position.
	 *
	 * @param array $defs  active stickers: sticker_id, text, text_color, bg_color, position, priority, rule_type, rule_value, show_pct, category_id, manufacturer_id
	 * @param array $facts product_id => fact
	 * @return array product_id => array(position => array(array('id', 'text', 'fg', 'bg')))
	 */
	public static function resolve($defs, $facts, $now, $limit) {
		$limit = max(1, (int)$limit);

		usort($defs, function ($a, $b) {
			$d = (int)$b['priority'] - (int)$a['priority'];

			return $d !== 0 ? $d : (int)$a['sticker_id'] - (int)$b['sticker_id'];
		});

		$out = array();

		foreach ($facts as $product_id => $fact) {
			foreach ($defs as $def) {
				$position = in_array($def['position'], self::$positions, true) ? $def['position'] : 'tl';
				$text = self::text($def, $fact);

				if ($text === '' || !self::matches($def, $fact, $now)) {
					continue;
				}

				if (isset($out[$product_id][$position]) && count($out[$product_id][$position]) >= $limit) {
					continue;
				}

				$out[$product_id][$position][] = array(
					'id'   => (int)$def['sticker_id'],
					'text' => $text,
					'fg'   => self::safeColor($def['text_color'], '#ffffff'),
					'bg'   => self::safeColor($def['bg_color'], '#1d1d1f')
				);
			}
		}

		return $out;
	}
}
