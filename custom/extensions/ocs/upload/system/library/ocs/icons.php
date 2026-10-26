<?php
// Extension: ocs. Icon names stored by admins (Font Awesome style "fa-mobile", "truck") -> symbol id of the theme's SVG sprite.
// Pure, no registry access (unit-testable). Owner: SPEC-042 (REQ-ICN-003).

namespace Ocs;

class Icons {
	const FALLBACK = 'i-dot';

	private static $map = array(
		'mobile' => 'i-mobile', 'mobile-phone' => 'i-mobile', 'smartphone' => 'i-mobile',
		'phone' => 'i-phone', 'phone-square' => 'i-phone',
		'laptop' => 'i-laptop', 'tablet' => 'i-tablet',
		'desktop' => 'i-desktop', 'television' => 'i-desktop', 'tv' => 'i-desktop', 'display' => 'i-desktop',
		'headphones' => 'i-headphones', 'headset' => 'i-headset', 'support' => 'i-headset', 'life-ring' => 'i-headset',
		'plug' => 'i-plug', 'bolt' => 'i-plug', 'camera' => 'i-camera', 'camera-retro' => 'i-camera', 'video-camera' => 'i-camera',
		'music' => 'i-music', 'puzzle-piece' => 'i-puzzle', 'cubes' => 'i-package', 'cube' => 'i-package', 'archive' => 'i-package', 'box' => 'i-package',
		'truck' => 'i-truck', 'shield' => 'i-shield', 'undo' => 'i-return', 'refresh' => 'i-return', 'reply' => 'i-return', 'repeat' => 'i-return',
		'check' => 'i-check', 'check-circle' => 'i-check', 'gift' => 'i-gift', 'tag' => 'i-tag', 'tags' => 'i-tag',
		'credit-card' => 'i-card', 'clock-o' => 'i-clock', 'clock' => 'i-clock', 'star' => 'i-star', 'heart' => 'i-heart',
		'home' => 'i-home', 'user' => 'i-user', 'shopping-cart' => 'i-cart', 'cart' => 'i-cart', 'search' => 'i-search',
		'map-marker' => 'i-pin', 'envelope' => 'i-mail', 'envelope-o' => 'i-mail', 'eye' => 'i-eye', 'filter' => 'i-filter',
		'bars' => 'i-menu', 'th' => 'i-grid', 'th-large' => 'i-grid', 'th-list' => 'i-view-list', 'list' => 'i-view-list',
		'exchange' => 'i-compare', 'calendar' => 'i-calendar', 'upload' => 'i-upload', 'info-circle' => 'i-info', 'exclamation-circle' => 'i-alert',
		'caret-down' => 'i-chevron-down', 'angle-down' => 'i-chevron-down', 'angle-right' => 'i-chevron-right', 'angle-left' => 'i-chevron-left', 'times' => 'i-x', 'book' => 'i-book', 'floppy-o' => 'i-save', 'check-square-o' => 'i-check',
		'caret-down' => 'i-chevron-down', 'angle-down' => 'i-chevron-down', 'angle-right' => 'i-chevron-right', 'angle-left' => 'i-chevron-left', 'times' => 'i-x', 'book' => 'i-book', 'floppy-o' => 'i-save', 'check-square-o' => 'i-check',
		'caret-down' => 'i-chevron-down', 'angle-down' => 'i-chevron-down', 'angle-right' => 'i-chevron-right', 'angle-left' => 'i-chevron-left', 'times' => 'i-x', 'book' => 'i-book', 'floppy-o' => 'i-save', 'check-square-o' => 'i-check',
		'caret-down' => 'i-chevron-down', 'angle-down' => 'i-chevron-down', 'angle-right' => 'i-chevron-right', 'angle-left' => 'i-chevron-left', 'times' => 'i-x', 'book' => 'i-book', 'floppy-o' => 'i-save', 'check-square-o' => 'i-check',
		'cloud-download' => 'i-download', 'download' => 'i-download', 'times-circle' => 'i-x-circle', 'pencil' => 'i-pencil', 'edit' => 'i-pencil', 'plus' => 'i-plus', 'minus' => 'i-minus',
		'trash' => 'i-trash', 'trash-o' => 'i-trash', 'share' => 'i-share',
	);

	/**
	 * @param string $name "fa-mobile", "fa fa-mobile", "mobile" or an "i-mobile" symbol id
	 * @return string symbol id, never empty
	 */
	public static function symbol($name) {
		$name = strtolower(trim((string)$name));
		$name = trim(preg_replace('/^fa\s+/', '', $name));
		$name = preg_replace('/^fa-/', '', $name);

		if (preg_match('/^i-[a-z0-9-]+$/', $name)) {
			return $name;
		}

		return isset(self::$map[$name]) ? self::$map[$name] : self::FALLBACK;
	}

	/** @return string[] names the admin can type (for the hint next to the icon field) */
	public static function names() {
		$names = array_keys(self::$map);

		sort($names);

		return $names;
	}

	/** @return string[] every symbol id this class can return (the sprite must contain all of them) */
	public static function symbols() {
		$symbols = array_values(array_unique(self::$map));

		$symbols[] = self::FALLBACK;

		sort($symbols);

		return $symbols;
	}
}
