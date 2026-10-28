<?php
// Extension: ocs_novaposhta. Pure text helpers: search normalisation and mapping of raw API records to table rows. Owner: SPEC-085.

namespace Ocs\Novaposhta;

class Text {
	/** Lower case, "№"/"N"/"#" markers and LIKE wildcards removed, spaces collapsed. */
	public static function norm($value) {
		$value = mb_strtolower(trim((string)$value), 'UTF-8');
		$value = str_replace(array('№', '#', '%', '_', '\\', '"', "'", '`'), ' ', $value);
		$value = preg_replace('/\bn\b/u', ' ', $value);

		return trim(preg_replace('/\s+/u', ' ', $value));
	}

	public static function row($entity, array $r) {
		if ($entity == 'areas') {
			return array(
				'ref'     => (string)$r['Ref'],
				'name_uk' => self::cut(self::val($r, 'Description'), 128),
				'name_ru' => self::cut(self::val($r, 'DescriptionRu') ?: self::val($r, 'Description'), 128)
			);
		}

		if ($entity == 'cities') {
			$uk = self::cut(self::val($r, 'Description'), 128);
			$ru = self::cut(self::val($r, 'DescriptionRu') ?: $uk, 128);

			return array(
				'ref'       => (string)$r['Ref'],
				'area_ref'  => self::val($r, 'Area'),
				'name_uk'   => $uk,
				'name_ru'   => $ru,
				'search_uk' => self::norm($uk),
				'search_ru' => self::norm($ru),
				'type_uk'   => self::cut(self::val($r, 'SettlementTypeDescription'), 64),
				'type_ru'   => self::cut(self::val($r, 'SettlementTypeDescriptionRu') ?: self::val($r, 'SettlementTypeDescription'), 64)
			);
		}

		$uk = self::cut(self::val($r, 'Description'), 255);
		$ru = self::cut(self::val($r, 'DescriptionRu') ?: $uk, 255);
		$number = (int)self::val($r, 'Number');

		return array(
			'ref'        => (string)$r['Ref'],
			'city_ref'   => self::val($r, 'CityRef'),
			'number'     => $number,
			'type'       => self::type($r),
			'name_uk'    => $uk,
			'name_ru'    => $ru,
			'search'     => self::cut(self::norm($number . ' ' . $uk . ' ' . $ru), 600),
			'schedule'   => json_encode(isset($r['Schedule']) && is_array($r['Schedule']) ? $r['Schedule'] : new \stdClass()),
			'max_weight' => (int)self::val($r, 'TotalMaxWeightAllowed'),
			'lat'        => self::cut(self::val($r, 'Latitude'), 20),
			'lng'        => self::cut(self::val($r, 'Longitude'), 20)
		);
	}

	/** branch | postomat | cargo (REQ-NP-020). */
	public static function type(array $r) {
		$category = mb_strtolower(self::val($r, 'CategoryOfWarehouse'), 'UTF-8');
		$name = mb_strtolower(self::val($r, 'Description') . ' ' . self::val($r, 'DescriptionRu'), 'UTF-8');

		if ($category == 'postomat' || strpos($name, 'поштомат') !== false || strpos($name, 'почтомат') !== false) {
			return 'postomat';
		}

		if (strpos($name, 'вантажне') !== false || strpos($name, 'грузовое') !== false || $category == 'cargo') {
			return 'cargo';
		}

		return 'branch';
	}

	/** Closed or not working points are not offered (a missing status counts as working). */
	public static function working(array $r) {
		return !isset($r['WarehouseStatus']) || $r['WarehouseStatus'] === '' || mb_strtolower($r['WarehouseStatus'], 'UTF-8') == 'working';
	}

	public static function hash(array $row) {
		return md5(json_encode($row));
	}

	private static function val(array $r, $key) {
		return isset($r[$key]) && is_scalar($r[$key]) ? trim((string)$r[$key]) : '';
	}

	private static function cut($value, $length) {
		return mb_substr($value, 0, $length, 'UTF-8');
	}
}
