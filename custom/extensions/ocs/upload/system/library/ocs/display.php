<?php
// Extension: ocs (theme). Stock label / article number switches (theme_ocs_show_stock, theme_ocs_show_sku): one place that removes the
// data from view data, so no template, module or JSON block can show what the admin switched off. Pure, no registry access.
// Owner: SPEC-044 (REQ-VIS-051).

namespace Ocs;

class Display {
	// keys of one product row that carry the stock label / the article number
	private static $stock_keys = array('ocs_stock', 'stock', 'availability');
	private static $sku_keys = array('model', 'sku');

	/**
	 * @param mixed $value setting value; an unset setting (null/'') means on
	 */
	public static function on($value) {
		return $value === null || $value === '' || (int)$value === 1;
	}

	/**
	 * Removes switched-off keys from the view data at every level down to five (product page, product rows of listings, tabbed modules,
	 * cart, wishlist, compare, confirm).
	 *
	 * @param array $data view data
	 * @param bool $stock stock label is shown
	 * @param bool $sku article number is shown
	 * @return array the data with the flags ocs_show_stock / ocs_show_sku
	 */
	public static function apply(array $data, $stock, $sku) {
		$drop = array();

		if (!$stock) {
			$drop = array_merge($drop, self::$stock_keys);
		}

		if (!$sku) {
			$drop = array_merge($drop, self::$sku_keys);
		}

		if ($drop) {
			$data = self::strip($data, $drop, 0);
		}

		$data['ocs_show_stock'] = (bool)$stock;
		$data['ocs_show_sku'] = (bool)$sku;

		return $data;
	}

	private static function strip(array $data, array $drop, $depth) {
		foreach ($data as $key => $value) {
			if (in_array($key, $drop, true) && !is_bool($value) && ($key === 'ocs_stock' || !is_array($value))) {
				unset($data[$key]);
			} elseif (is_array($value) && $depth < 5) {
				$data[$key] = self::strip($value, $drop, $depth + 1);
			}
		}

		return $data;
	}
}
