<?php
// Extension: ocs_bookmarks. Wishlist / compare cookies: parsing and serialising product id lists. Pure, no registry access.
// Owner: SPEC-044 (REQ-BKM-001, REQ-BKM-003), SPEC-057 (compare groups and limits).

namespace Ocs;

class Bookmarks {
	const WISH = 'ocs_wish';
	const COMPARE = 'ocs_cmp';
	const WISH_MAX = 100;
	const COMPARE_MAX = 12;
	const COMPARE_PER_CATEGORY = 4;
	const DAYS = 90;

	/**
	 * @param mixed $raw cookie value, "12,15,15,abc,-3"
	 * @param int $max most recent ids kept
	 * @return array unique positive ints, oldest first; garbage is dropped
	 */
	public static function parse($raw, $max) {
		$ids = array();

		if (!is_string($raw) || $raw === '' || strlen($raw) > 2000) {
			return $ids;
		}

		foreach (explode(',', $raw) as $part) {
			$part = trim($part);

			if ($part !== '' && ctype_digit($part) && (int)$part > 0) {
				$ids[(int)$part] = (int)$part;
			}
		}

		$ids = array_values($ids);

		return count($ids) > $max ? array_slice($ids, -$max) : $ids;
	}

	public static function serialize(array $ids) {
		return implode(',', array_map('intval', $ids));
	}

	/**
	 * @return array the list after the action (add moves the id to the end and cuts to $max; remove drops it)
	 */
	public static function change(array $ids, $action, $id, $max) {
		$id = (int)$id;
		$ids = array_values(array_filter($ids, function ($x) use ($id) {
			return (int)$x !== $id;
		}));

		if ($action === 'add' && $id > 0) {
			$ids[] = $id;
		}

		return count($ids) > $max ? array_slice($ids, -$max) : $ids;
	}

	/**
	 * SPEC-057 REQ-CMP-001: the compare category of a product is its deepest category (highest path level, ties: lowest id); none -> 0.
	 *
	 * @param array $rows [['category_id' => int|null, 'level' => int|null], ...] the categories of one product
	 * @return int
	 */
	public static function deepest(array $rows) {
		$best = 0;
		$level = -1;

		foreach ($rows as $row) {
			$id = (int)$row['category_id'];

			if ($id <= 0) {
				continue;
			}

			$l = (int)$row['level'];

			if ($l > $level || ($l === $level && $id < $best)) {
				$best = $id;
				$level = $l;
			}
		}

		return $best;
	}

	/**
	 * SPEC-057 REQ-CMP-002: ids grouped by compare category. Ids missing from $categoryOf (disabled / deleted products) are dropped.
	 *
	 * @param array $ids oldest first
	 * @param array $categoryOf product_id => category_id (0 = no category)
	 * @return array category_id => ids (oldest first); the group of the most recently added product comes first
	 */
	public static function groups(array $ids, array $categoryOf) {
		$groups = array();

		foreach ($ids as $id) {
			if (!isset($categoryOf[$id])) {
				continue;
			}

			$category = (int)$categoryOf[$id];

			// the group of the newest product moves to the end; the result is reversed below
			$members = isset($groups[$category]) ? $groups[$category] : array();
			unset($groups[$category]);
			$members[] = $id;
			$groups[$category] = $members;
		}

		return array_reverse($groups, true);
	}

	/**
	 * SPEC-057 REQ-CMP-006: at most $perCategory newest ids per compare category and $total overall. Ids without a known category are kept
	 * (they only count against $total), so a temporarily disabled product does not vanish from the list.
	 *
	 * @return array the ids that stay, original order
	 */
	public static function trim(array $ids, array $categoryOf, $total, $perCategory) {
		$seen = array();
		$keep = array();

		for ($i = count($ids) - 1; $i >= 0; $i--) {
			$id = $ids[$i];

			if (isset($categoryOf[$id])) {
				$category = (int)$categoryOf[$id];
				$seen[$category] = isset($seen[$category]) ? $seen[$category] + 1 : 1;

				if ($seen[$category] > $perCategory) {
					continue;
				}
			}

			$keep[] = $id;
		}

		return array_reverse(array_slice($keep, 0, $total));
	}
}
