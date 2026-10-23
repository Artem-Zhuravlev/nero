<?php
// Extension: ocs_request. Stock rules shared by pre-order, back-in-stock and the quick view (pure, tested without a database). Owner: SPEC-037 (REQ-OPT-001).

namespace Ocs;

class Stock {
	/**
	 * Is an option value unavailable? Core hides values with "subtract stock" on and no quantity.
	 */
	public static function optionOut($subtract, $quantity) {
		return (int)$subtract === 1 && (int)$quantity <= 0;
	}

	/**
	 * Does the selection need the pre-order / notify button instead of "Add to cart"?
	 *
	 * @param int   $productQuantity quantity of the product
	 * @param bool  $productApplies  result of OrderRequest::preorderApplies() for the product itself
	 * @param int[] $selected        chosen product_option_value ids
	 * @param array $out             product_option_value_id => true for the values that are out of stock
	 */
	public static function selectionNeedsRequest($productApplies, $selected, $out) {
		if ($productApplies) {
			return true;
		}

		foreach (array_map('intval', (array)$selected) as $id) {
			if (!empty($out[$id])) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Is the option-level pre-order rule on for the product? An explicit per-product "on" wins, "off" disables, else the global switch.
	 */
	public static function optionRuleOn($global, $override) {
		$mode = isset($override['mode']) ? $override['mode'] : '';

		if ($mode === 'off') {
			return false;
		}

		return $mode === 'on' || (bool)$global;
	}

	/**
	 * Pre-order applies when the product itself qualifies, or when the rule is on and a chosen option value is out of stock.
	 */
	public static function preorderAppliesFor($global, $quantity, $override, $selected, $out) {
		if (OrderRequest::preorderApplies($global, $quantity, $override)) {
			return true;
		}

		return self::optionRuleOn($global, $override) && self::selectionNeedsRequest(false, $selected, $out);
	}

	/**
	 * Flat list of the chosen option value ids from the posted option[] array (selects, radios, checkboxes).
	 */
	public static function selectedValueIds($post) {
		$ids = array();

		foreach ((array)$post as $value) {
			foreach ((array)$value as $v) {
				if (is_scalar($v) && ctype_digit((string)$v)) {
					$ids[] = (int)$v;
				}
			}
		}

		return array_values(array_unique($ids));
	}
}
