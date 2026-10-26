<?php
// Extension: ocs_bookmarks. Wishlist / compare cookies: parsing and serialising product id lists. Pure, no registry access.
// Owner: SPEC-044 (REQ-BKM-001, REQ-BKM-003).

namespace Ocs;

class Bookmarks {
	const WISH = 'ocs_wish';
	const COMPARE = 'ocs_cmp';
	const WISH_MAX = 100;
	const COMPARE_MAX = 4;
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
}
