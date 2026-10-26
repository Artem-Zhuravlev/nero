<?php
// Extension: ocs_menu. Pure category tree helpers shared by the mega menu, category wall and side menu. Owner: SPEC-027.
// No registry access (unit-testable, SPEC-021).

namespace Ocs;

class CategoryTree {
	/**
	 * Builds a nested tree from flat rows.
	 *
	 * @param array $rows      each: category_id, parent_id, name, sort_order (optional), plus any extra keys that are kept
	 * @param int   $max_level number of levels to keep (1 = only roots)
	 * @return array roots, every node has 'children' and 'level'
	 */
	public static function build($rows, $max_level) {
		$max_level = max(1, (int)$max_level);
		$by_parent = array();
		$ids = array();

		foreach ($rows as $row) {
			$ids[(int)$row['category_id']] = true;
		}

		foreach ($rows as $row) {
			$parent = (int)$row['parent_id'];

			// rows whose parent is missing (disabled or other store) are not reachable
			if ($parent !== 0 && !isset($ids[$parent])) {
				continue;
			}

			$by_parent[$parent][] = $row;
		}

		$seen = array();

		return self::branch($by_parent, 0, 1, $max_level, $seen);
	}

	private static function branch($by_parent, $parent, $level, $max_level, &$seen) {
		$out = array();

		if (empty($by_parent[$parent])) {
			return $out;
		}

		foreach ($by_parent[$parent] as $row) {
			$id = (int)$row['category_id'];

			if (isset($seen[$id])) {
				continue; // cycle guard
			}

			$seen[$id] = true;

			$row['level'] = $level;
			$row['children'] = $level < $max_level ? self::branch($by_parent, $id, $level + 1, $max_level, $seen) : array();

			$out[] = $row;
		}

		return $out;
	}

	/**
	 * Adds 'path' (1_5_7) and 'href' to every node. $href receives the path string.
	 */
	public static function decorate($nodes, $href, $prefix = array()) {
		foreach ($nodes as $i => $node) {
			$ids = array_merge($prefix, array((int)$node['category_id']));

			$nodes[$i]['path'] = implode('_', $ids);
			$nodes[$i]['href'] = call_user_func($href, $nodes[$i]['path']);
			$nodes[$i]['children'] = self::decorate($node['children'], $href, $ids);
		}

		return $nodes;
	}

	/**
	 * '1_5_7' -> array(1, 5, 7); invalid parts are dropped.
	 */
	public static function pathIds($path) {
		$ids = array();

		foreach (explode('_', (string)$path) as $part) {
			if (ctype_digit($part) && (int)$part > 0) {
				$ids[] = (int)$part;
			}
		}

		return $ids;
	}

	/**
	 * Marks nodes on the active path: 'active' for every ancestor and the current node, 'current' for the last one.
	 */
	public static function markActive($nodes, $active_ids) {
		$current = $active_ids ? end($active_ids) : 0;

		foreach ($nodes as $i => $node) {
			$id = (int)$node['category_id'];

			$nodes[$i]['active'] = in_array($id, $active_ids, true);
			$nodes[$i]['current'] = $id === $current;
			$nodes[$i]['children'] = self::markActive($node['children'], $active_ids);
		}

		return $nodes;
	}

	/**
	 * Sorts siblings (recursively) by 'sort' (sort_order, then name) or by 'name'.
	 */
	public static function sort($nodes, $by) {
		usort($nodes, function ($a, $b) use ($by) {
			if ($by !== 'name') {
				$d = (int)(isset($a['sort_order']) ? $a['sort_order'] : 0) - (int)(isset($b['sort_order']) ? $b['sort_order'] : 0);

				if ($d !== 0) {
					return $d;
				}
			}

			return strcmp(mb_strtolower((string)$a['name'], 'UTF-8'), mb_strtolower((string)$b['name'], 'UTF-8'));
		});

		foreach ($nodes as $i => $node) {
			$nodes[$i]['children'] = self::sort($node['children'], $by);
		}

		return $nodes;
	}

	/**
	 * Depth-first list of all nodes (children keys kept).
	 */
	public static function flatten($nodes) {
		$out = array();

		foreach ($nodes as $node) {
			$out[] = $node;

			foreach (self::flatten($node['children']) as $child) {
				$out[] = $child;
			}
		}

		return $out;
	}

	/**
	 * Picks nodes by id list, keeping the list order; unknown ids are skipped.
	 *
	 * @param int[] $ids
	 */
	public static function pick($nodes, $ids) {
		$by_id = array();

		foreach (self::flatten($nodes) as $node) {
			$by_id[(int)$node['category_id']] = $node;
		}

		$out = array();

		foreach ($ids as $id) {
			if (isset($by_id[(int)$id])) {
				$out[] = $by_id[(int)$id];
			}
		}

		return $out;
	}

	/**
	 * Groups manufacturer rows (category_id, manufacturer_id, name, cnt) per category: most products first,
	 * then name, at most $limit per category.
	 *
	 * @return array category_id => array(array('manufacturer_id' => , 'name' => ), ...)
	 */
	public static function groupBrands($rows, $limit) {
		$by = array();

		foreach ($rows as $row) {
			$by[(int)$row['category_id']][] = $row;
		}

		$out = array();

		foreach ($by as $category_id => $list) {
			usort($list, function ($a, $b) {
				$d = (int)$b['cnt'] - (int)$a['cnt'];

				return $d !== 0 ? $d : strcmp(mb_strtolower((string)$a['name'], 'UTF-8'), mb_strtolower((string)$b['name'], 'UTF-8'));
			});

			foreach (array_slice($list, 0, max(1, (int)$limit)) as $row) {
				$out[$category_id][] = array('manufacturer_id' => (int)$row['manufacturer_id'], 'name' => $row['name']);
			}
		}

		return $out;
	}

	/**
	 * Renders an icon value: a stored icon name ("fa-mobile", "truck", "i-mobile") -> sprite symbol id (SVG), otherwise an image path.
	 *
	 * @return array('type' => 'svg'|'image'|'', 'value' => string)
	 */
	public static function icon($value) {
		$value = trim((string)$value);

		if ($value === '') {
			return array('type' => '', 'value' => '');
		}

		if (preg_match('/^(fa-|i-)[a-z0-9-]+$/', $value)) {
			return array('type' => 'svg', 'value' => \Ocs\Icons::symbol($value));
		}

		return array('type' => 'image', 'value' => $value);
	}
}
