<?php
// Extension: ocs_tradein. Photo upload checks and helpers (testable without a web server). Owner: SPEC-036 (REQ-CRD-020).

namespace Ocs;

class Tradein {
	const MAX_FILES = 3;
	const MAX_BYTES = 2097152;

	/** detected image type => extension; anything else is refused whatever the client says */
	const TYPES = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif');

	/**
	 * $_FILES['photos'] (multiple) or a single entry -> list of array(name, tmp_name, size, error); empty slots are dropped.
	 */
	public static function normalize($files) {
		$out = array();

		if (!is_array($files) || !isset($files['name'])) {
			return $out;
		}

		if (!is_array($files['name'])) {
			$files = array_map(function ($v) {
				return array($v);
			}, $files);
		}

		foreach (array_keys($files['name']) as $i) {
			if ((int)$files['error'][$i] === UPLOAD_ERR_NO_FILE) {
				continue;
			}

			$out[] = array('name' => (string)$files['name'][$i], 'tmp_name' => (string)$files['tmp_name'][$i], 'size' => (int)$files['size'][$i], 'error' => (int)$files['error'][$i]);
		}

		return $out;
	}

	/**
	 * Checks the uploads: at most 3 files, each an uploaded image of at most 2 MB.
	 *
	 * @param callable|null $isUploaded replaces is_uploaded_file() in tests
	 * @return array array('error' => '' | count | size | type | upload, 'files' => array(tmp_name, ext))
	 */
	public static function check($entries, $isUploaded = null) {
		$isUploaded = $isUploaded ?: 'is_uploaded_file';
		$files = array();

		if (count($entries) > self::MAX_FILES) {
			return array('error' => 'count', 'files' => array());
		}

		foreach ($entries as $e) {
			if ($e['error'] === UPLOAD_ERR_INI_SIZE || $e['error'] === UPLOAD_ERR_FORM_SIZE || $e['size'] > self::MAX_BYTES) {
				return array('error' => 'size', 'files' => array());
			}

			if ($e['error'] !== UPLOAD_ERR_OK || !call_user_func($isUploaded, $e['tmp_name'])) {
				return array('error' => 'upload', 'files' => array());
			}

			if (filesize($e['tmp_name']) > self::MAX_BYTES) {
				return array('error' => 'size', 'files' => array());
			}

			$info = @getimagesize($e['tmp_name']);
			$mime = $info && isset($info['mime']) ? $info['mime'] : '';

			if (!isset(self::TYPES[$mime])) {
				return array('error' => 'type', 'files' => array());
			}

			$files[] = array('tmp_name' => $e['tmp_name'], 'ext' => self::TYPES[$mime]);
		}

		return array('error' => '', 'files' => $files);
	}

	/**
	 * Name under which a photo is stored: random, with the extension of the detected type.
	 */
	public static function storedName($ext) {
		return bin2hex(random_bytes(16)) . '.' . $ext;
	}

	public static function validStoredName($name) {
		return is_string($name) && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif)$/', $name) === 1;
	}

	/**
	 * Comma separated stored names -> the valid ones only.
	 */
	public static function parseNames($text) {
		return array_values(array_filter(explode(',', (string)$text), array(__CLASS__, 'validStoredName')));
	}
}
