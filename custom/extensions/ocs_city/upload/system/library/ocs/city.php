<?php
// Extension: ocs_city. Pure rules of the city selector, delivery lines and order tracking (tested without a database). Owner: SPEC-032.

namespace Ocs;

class City {
	const METHODS = array('pickup', 'post', 'courier');

	/**
	 * Current city: the cookie value when it is an active city, else the default one, else the first one, else 0.
	 *
	 * @param mixed $cookie   raw cookie value
	 * @param int[] $ids      active city ids in display order
	 * @param int   $default  default city id
	 */
	public static function current($cookie, $ids, $default) {
		$ids = array_map('intval', (array)$ids);
		$cookie = ctype_digit((string)$cookie) ? (int)$cookie : 0;

		if ($cookie && in_array($cookie, $ids, true)) {
			return $cookie;
		}

		if ((int)$default && in_array((int)$default, $ids, true)) {
			return (int)$default;
		}

		return $ids ? $ids[0] : 0;
	}

	public static function validCutoff($value) {
		return $value === '' || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)$value) === 1;
	}

	/**
	 * Delivery estimate: "tomorrow" while the cut-off time has not passed, else the day range, else nothing.
	 *
	 * @param array  $rule days_min, days_max, cutoff ("HH:MM" or '')
	 * @param string $now  current time "HH:MM" in the store time zone
	 * @return array array('type' => tomorrow|days|none, 'min' => int, 'max' => int)
	 */
	public static function eta($rule, $now) {
		$cutoff = isset($rule['cutoff']) ? (string)$rule['cutoff'] : '';
		$min = max(0, (int)(isset($rule['days_min']) ? $rule['days_min'] : 0));
		$max = max($min, (int)(isset($rule['days_max']) ? $rule['days_max'] : 0));

		if ($cutoff !== '' && self::validCutoff($cutoff) && strcmp((string)$now, $cutoff) < 0) {
			return array('type' => 'tomorrow', 'min' => 1, 'max' => 1);
		}

		if ($max > 0) {
			return array('type' => 'days', 'min' => $min, 'max' => $max);
		}

		return array('type' => 'none', 'min' => 0, 'max' => 0);
	}

	/**
	 * Price line: free (price 0 or amount reaches the threshold) or a price with an optional "free from" threshold.
	 *
	 * @return array array('free' => bool, 'price' => float, 'free_from' => float)
	 */
	public static function price($rule, $amount) {
		$price = max(0.0, (float)(isset($rule['price']) ? $rule['price'] : 0));
		$from = max(0.0, (float)(isset($rule['free_from']) ? $rule['free_from'] : 0));

		if ($price <= 0 || ($from > 0 && (float)$amount >= $from)) {
			return array('free' => true, 'price' => 0.0, 'free_from' => 0.0);
		}

		return array('free' => false, 'price' => $price, 'free_from' => $from);
	}

	/**
	 * Last nine digits of a phone number (country prefixes and formatting do not matter); '' when it is too short.
	 */
	public static function phoneKey($value) {
		$digits = preg_replace('/\D+/', '', (string)$value);

		return strlen($digits) >= 9 ? substr($digits, -9) : '';
	}

	/**
	 * Second factor of the tracking page: the e-mail or the phone typed by the visitor must match the order.
	 */
	public static function trackingMatches($order, $contact) {
		$contact = trim((string)$contact);

		if ($contact === '' || !$order) {
			return false;
		}

		if (strpos($contact, '@') !== false) {
			return strtolower($contact) === strtolower((string)$order['email']) && trim((string)$order['email']) !== '';
		}

		$key = self::phoneKey($contact);

		return $key !== '' && $key === self::phoneKey($order['telephone']);
	}

	/**
	 * Map link is shown only for http(s) addresses.
	 */
	public static function safeLink($value) {
		$value = trim((string)$value);

		return preg_match('~^https?://[^\s<>"\']+$~i', $value) === 1 ? $value : '';
	}

	/**
	 * Admin form of a city: every language needs a name; delivery rules are checked per method.
	 *
	 * @param array $post  decoded POST (raw text)
	 * @param int[] $langs language ids
	 * @return array array('errors' => field => code, 'data' => ...)
	 */
	public static function cleanCity($post, $langs) {
		$errors = array();
		$names = array();

		foreach ($langs as $id) {
			$name = isset($post['name'][$id]) ? trim(preg_replace('/\s+/u', ' ', (string)$post['name'][$id])) : '';

			if ($name === '' || mb_strlen($name, 'UTF-8') > 128) {
				$errors['name'] = 'name';
			}

			$names[$id] = $name;
		}

		$rules = array();

		foreach (self::METHODS as $method) {
			$r = isset($post['rule'][$method]) && is_array($post['rule'][$method]) ? $post['rule'][$method] : array();

			if (empty($r['status'])) {
				continue;
			}

			$clean = array(
				'status'    => 1,
				'price'     => isset($r['price']) && is_numeric($r['price']) ? max(0.0, (float)$r['price']) : 0.0,
				'free_from' => isset($r['free_from']) && is_numeric($r['free_from']) ? max(0.0, (float)$r['free_from']) : 0.0,
				'days_min'  => isset($r['days_min']) && ctype_digit((string)$r['days_min']) ? min(90, (int)$r['days_min']) : 0,
				'days_max'  => isset($r['days_max']) && ctype_digit((string)$r['days_max']) ? min(90, (int)$r['days_max']) : 0,
				'cutoff'    => isset($r['cutoff']) ? trim((string)$r['cutoff']) : '',
				'notes'     => array()
			);

			if (!self::validCutoff($clean['cutoff'])) {
				$errors['rule_' . $method] = 'cutoff';
			}

			if ($clean['days_max'] < $clean['days_min']) {
				$errors['rule_' . $method] = 'days';
			}

			foreach ($langs as $id) {
				$note = isset($r['notes'][$id]) ? trim((string)$r['notes'][$id]) : '';

				$clean['notes'][$id] = mb_substr($note, 0, 255, 'UTF-8');
			}

			$rules[$method] = $clean;
		}

		return array('errors' => $errors, 'data' => array(
			'sort_order' => isset($post['sort_order']) ? (int)$post['sort_order'] : 0,
			'is_default' => !empty($post['is_default']) ? 1 : 0,
			'status'     => !empty($post['status']) ? 1 : 0,
			'names'      => $names,
			'rules'      => $rules
		));
	}

	/**
	 * Admin form of a store.
	 *
	 * @param int[] $cities existing city ids
	 */
	public static function cleanStore($post, $langs, $cities) {
		$errors = array();
		$desc = array();

		foreach ($langs as $id) {
			$row = isset($post['desc'][$id]) && is_array($post['desc'][$id]) ? $post['desc'][$id] : array();
			$name = isset($row['name']) ? trim((string)$row['name']) : '';
			$address = isset($row['address']) ? trim((string)$row['address']) : '';

			if ($name === '' || mb_strlen($name, 'UTF-8') > 128) {
				$errors['name'] = 'name';
			}

			if ($address === '' || mb_strlen($address, 'UTF-8') > 255) {
				$errors['address'] = 'address';
			}

			$desc[$id] = array('name' => $name, 'address' => $address, 'hours' => mb_substr(isset($row['hours']) ? trim((string)$row['hours']) : '', 0, 255, 'UTF-8'));
		}

		$city_id = isset($post['city_id']) ? (int)$post['city_id'] : 0;

		if (!in_array($city_id, array_map('intval', (array)$cities), true)) {
			$errors['city'] = 'city';
		}

		$phone = isset($post['phone']) ? trim((string)$post['phone']) : '';

		if ($phone !== '' && !preg_match('/^[\d+\-\s()]{5,32}$/', $phone)) {
			$errors['phone'] = 'phone';
		}

		$link = isset($post['map_link']) ? trim((string)$post['map_link']) : '';

		if ($link !== '' && (self::safeLink($link) === '' || strlen($link) > 255)) {
			$errors['map'] = 'map';
		}

		$lat = self::coordinate(isset($post['latitude']) ? $post['latitude'] : '', 90);
		$lon = self::coordinate(isset($post['longitude']) ? $post['longitude'] : '', 180);

		if ((isset($post['latitude']) && trim((string)$post['latitude']) !== '' && $lat === '') || (isset($post['longitude']) && trim((string)$post['longitude']) !== '' && $lon === '')) {
			$errors['coords'] = 'coords';
		}

		return array('errors' => $errors, 'data' => array(
			'city_id'    => $city_id,
			'phone'      => $phone,
			'map_link'   => $link,
			'latitude'   => $lat,
			'longitude'  => $lon,
			'sort_order' => isset($post['sort_order']) ? (int)$post['sort_order'] : 0,
			'status'     => !empty($post['status']) ? 1 : 0,
			'desc'       => $desc
		));
	}

	/**
	 * Latitude / longitude as a float in range or '' (empty = not set).
	 */
	public static function coordinate($value, $max) {
		$value = trim((string)$value);

		if ($value === '' || !is_numeric($value) || abs((float)$value) > $max) {
			return '';
		}

		return (string)round((float)$value, 6);
	}
}
