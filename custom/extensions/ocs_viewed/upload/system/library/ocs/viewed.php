<?php
// Extension: ocs_viewed. Pure list helpers for the "recently viewed" history. Owner: SPEC-030 (REQ-VWD-001, 003, 004).
// No registry access: unit-testable (SPEC-021).

namespace Ocs;

class Viewed {
	const MAX = 20;

	/**
	 * Cookie value "12,7,40" -> array(12, 7, 40): positive integers only, no duplicates, newest first, at most MAX.
	 */
	public static function parse($value) {
		$out = array();

		foreach (explode(',', (string)$value) as $part) {
			$part = trim($part);

			if (ctype_digit($part) && (int)$part > 0 && !in_array((int)$part, $out, true)) {
				$out[] = (int)$part;
			}

			if (count($out) >= self::MAX) {
				break;
			}
		}

		return $out;
	}

	/**
	 * Puts a product id at the front of the list (moving it if present) and cuts the list to MAX.
	 *
	 * @param int[] $list
	 * @return int[]
	 */
	public static function push($list, $product_id) {
		$product_id = (int)$product_id;

		if ($product_id < 1) {
			return array_slice(array_values($list), 0, self::MAX);
		}

		$out = array($product_id);

		foreach ($list as $id) {
			if ((int)$id !== $product_id) {
				$out[] = (int)$id;
			}
		}

		return array_slice($out, 0, self::MAX);
	}

	/**
	 * Union of the account history (newest first) and the browser list (newest first): the account order wins for ids it knows,
	 * browser-only ids follow. No duplicates, at most MAX.
	 *
	 * @param int[] $account
	 * @param int[] $cookie
	 * @return int[]
	 */
	public static function merge($account, $cookie) {
		$out = array();

		foreach (array_merge($account, $cookie) as $id) {
			if ((int)$id > 0 && !in_array((int)$id, $out, true)) {
				$out[] = (int)$id;
			}
		}

		return array_slice($out, 0, self::MAX);
	}

	/**
	 * Ids to show: the current product is left out, order kept, limited.
	 *
	 * @param int[] $list
	 * @return int[]
	 */
	public static function visible($list, $exclude, $limit) {
		$out = array();

		foreach ($list as $id) {
			if ((int)$id !== (int)$exclude) {
				$out[] = (int)$id;
			}
		}

		return array_slice($out, 0, max(1, (int)$limit));
	}
}
