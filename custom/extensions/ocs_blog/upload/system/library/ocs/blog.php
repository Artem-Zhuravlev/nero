<?php
namespace Ocs;

/**
 * Pure helpers of the blog extension (SPEC-084): social links, author JSON-LD person, voter key, language settings.
 * No framework access here, so the PHP test runner can load it alone.
 */
class Blog {
	/** Social networks of an author: key => label (language file keys are entry_social_<key>). */
	const SOCIAL = array('facebook', 'instagram', 'x', 'youtube', 'linkedin', 'telegram', 'tiktok', 'website');

	const VOTES_PER_HOUR = 20;

	public static function e($value) {
		return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Keeps only known networks with http(s) links (REQ-BLG-002); anything else is dropped.
	 *
	 * @return array key => url
	 */
	public static function cleanSocial($input) {
		$out = array();

		if (!is_array($input)) {
			return $out;
		}

		foreach (self::SOCIAL as $key) {
			$url = isset($input[$key]) ? trim((string)$input[$key]) : '';

			if ($url !== '' && preg_match('#^https?://[^\s<>"\']+$#i', $url) && strlen($url) <= 255) {
				$out[$key] = $url;
			}
		}

		return $out;
	}

	public static function decodeSocial($json) {
		$data = json_decode((string)$json, true);

		return self::cleanSocial($data);
	}

	/**
	 * schema.org Person of an author (REQ-BLG-005). Null without a name.
	 */
	public static function person(array $author) {
		$name = trim(isset($author['name']) ? (string)$author['name'] : '');

		if ($name === '') {
			return null;
		}

		$node = array('@type' => 'Person', 'name' => $name);

		if (!empty($author['position'])) {
			$node['jobTitle'] = trim((string)$author['position']);
		}

		if (!empty($author['description'])) {
			$node['description'] = mb_substr(trim(strip_tags((string)$author['description'])), 0, 300);
		}

		if (!empty($author['image_url'])) {
			$node['image'] = (string)$author['image_url'];
		}

		if (!empty($author['social']) && is_array($author['social'])) {
			$node['sameAs'] = array_values($author['social']);
		}

		return $node;
	}

	/**
	 * Date shown for an article: date_available, or date_added when empty / 0000-00-00 (REQ-BLG-011).
	 */
	public static function articleDate($date_available, $date_added) {
		$d = (string)$date_available;

		if ($d === '' || strpos($d, '0000-00-00') === 0 || !strtotime($d)) {
			$d = (string)$date_added;
		}

		return $d;
	}

	/**
	 * Voter identity: customer id when logged in, otherwise a salted hash of IP and user agent (REQ-BLG-033).
	 */
	public static function voterKey($customer_id, $ip, $agent, $salt = '') {
		if ((int)$customer_id > 0) {
			return 'c' . (int)$customer_id;
		}

		return 'g' . substr(sha1($salt . '|' . $ip . '|' . $agent), 0, 30);
	}

	/**
	 * New vote state: same value again removes the vote, another value replaces it.
	 *
	 * @param int $old stored vote (-1, 0, 1)
	 * @param int $new requested vote (-1 or 1)
	 * @return int the stored vote after the click
	 */
	public static function nextVote($old, $new) {
		$new = (int)$new > 0 ? 1 : -1;

		return (int)$old === $new ? 0 : $new;
	}

	/**
	 * Settings per language (REQ-BLG-021): language id, then default language, then the old single keys.
	 *
	 * @param string $json configblog_description
	 * @param array  $legacy old configblog_* values keyed name, html_h1, meta_title, meta_description, meta_keyword
	 */
	public static function langSettings($json, $language_id, $default_language_id, array $legacy) {
		$all = json_decode((string)$json, true);
		$all = is_array($all) ? $all : array();
		$keys = array('name', 'html_h1', 'meta_title', 'meta_description', 'meta_keyword');
		$out = array();

		foreach ($keys as $key) {
			$value = '';

			foreach (array((int)$language_id, (int)$default_language_id) as $id) {
				if (isset($all[$id][$key]) && trim((string)$all[$id][$key]) !== '') {
					$value = (string)$all[$id][$key];
					break;
				}
			}

			$out[$key] = $value !== '' ? $value : (isset($legacy[$key]) ? (string)$legacy[$key] : '');
		}

		return $out;
	}

	/**
	 * Cleans the posted per-language settings: only known languages and keys, trimmed.
	 */
	public static function cleanLangSettings($post, array $language_ids) {
		$keys = array('name', 'html_h1', 'meta_title', 'meta_description', 'meta_keyword');
		$out = array();

		foreach ($language_ids as $id) {
			foreach ($keys as $key) {
				$out[(int)$id][$key] = isset($post[$id][$key]) && is_string($post[$id][$key]) ? trim($post[$id][$key]) : '';
			}
		}

		return $out;
	}
}
