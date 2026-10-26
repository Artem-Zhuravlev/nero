<?php
// Extension: ocs_product_extras. YouTube ids: parsing, URLs and the "has video" flag of product rows. Pure, no registry access (unit-testable).
// Owner: SPEC-048 (REQ-VID-001, 003, 004).

namespace Ocs;

class Video {
	/**
	 * @param mixed $input a YouTube URL (watch, youtu.be, embed, shorts, live) or a bare 11-character id
	 * @return string the id or '' when the input is not a YouTube video
	 */
	public static function parse($input) {
		$s = trim(html_entity_decode((string)$input, ENT_QUOTES, 'UTF-8'));

		if ($s === '' || strlen($s) > 300 || preg_match('/[\x00-\x1f\s]/', $s)) {
			return '';
		}

		if (self::valid($s)) {
			return $s;
		}

		$parts = parse_url(preg_match('#^https?://#i', $s) ? $s : 'https://' . $s);

		if (!$parts || empty($parts['host'])) {
			return '';
		}

		$host = strtolower(preg_replace('/^(www|m|music)\./i', '', $parts['host']));
		$path = isset($parts['path']) ? trim($parts['path'], '/') : '';
		$id = '';

		if ($host === 'youtu.be') {
			$id = explode('/', $path)[0];
		} elseif ($host === 'youtube.com' || $host === 'youtube-nocookie.com') {
			if ($path === 'watch' && isset($parts['query'])) {
				parse_str($parts['query'], $q);
				$id = isset($q['v']) && is_string($q['v']) ? $q['v'] : '';
			} elseif (preg_match('#^(embed|shorts|live|v)/([^/]+)#', $path, $m)) {
				$id = $m[2];
			}
		}

		return self::valid($id) ? $id : '';
	}

	public static function valid($id) {
		return is_string($id) && (bool)preg_match('/^[A-Za-z0-9_-]{11}$/', $id);
	}

	public static function poster($id) {
		return self::valid($id) ? 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg' : '';
	}

	public static function embed($id) {
		return self::valid($id) ? 'https://www.youtube-nocookie.com/embed/' . $id . '?autoplay=1&rel=0' : '';
	}

	/**
	 * @return array unique product ids of every row (array with a numeric product_id) found in the view data, to the depth the templates use
	 */
	public static function rowIds($data, $depth = 0) {
		$ids = array();

		if (!is_array($data) || $depth > 4) {
			return $ids;
		}

		foreach ($data as $value) {
			if (!is_array($value)) {
				continue;
			}

			if (isset($value['product_id']) && is_numeric($value['product_id'])) {
				$ids[(int)$value['product_id']] = (int)$value['product_id'];
			}

			foreach (self::rowIds($value, $depth + 1) as $id) {
				$ids[$id] = $id;
			}
		}

		return array_values($ids);
	}

	/**
	 * Sets has_video on every product row whose id is in $with.
	 */
	public static function mark($data, array $with, $depth = 0) {
		if (!is_array($data) || $depth > 4) {
			return $data;
		}

		$flip = array_flip($with);

		foreach ($data as $key => $value) {
			if (!is_array($value)) {
				continue;
			}

			if (isset($value['product_id']) && is_numeric($value['product_id']) && isset($flip[(int)$value['product_id']])) {
				$value['has_video'] = true;
			}

			$data[$key] = self::mark($value, $with, $depth + 1);
		}

		return $data;
	}
}
