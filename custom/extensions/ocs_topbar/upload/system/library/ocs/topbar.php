<?php
// Extension: ocs_topbar. Pure helper that turns the top bar settings into template data. Owner: SPEC-082.
// No registry access: everything comes in as arguments so it is unit-testable (SPEC-021). Escaping and URL checks come from \Ocs\Shell.

namespace Ocs;

class Topbar {
	private static $backgrounds = array('accent', 'dark', 'light', 'custom');

	/**
	 * Top bar data for the given language and moment, or null when there is nothing to show (REQ-TBR-003, 004, 007).
	 *
	 * @param array  $settings keys: l => [code => [text, url, btn_label, btn_url]], from, to (Y-m-d), bg, bg_custom, closable, btn_style, blank
	 * @param string $code     language directory code
	 * @param int    $now      unix time
	 */
	public static function build($settings, $code, $now) {
		$l = isset($settings['l'][$code]) && is_array($settings['l'][$code]) ? $settings['l'][$code] : array();
		$text = isset($l['text']) ? trim((string)$l['text']) : '';

		if ($text === '') {
			return null;
		}

		$from = !empty($settings['from']) ? strtotime($settings['from'] . ' 00:00:00') : false;
		$to = !empty($settings['to']) ? strtotime($settings['to'] . ' 23:59:59') : false;

		if (($from && $now < $from) || ($to && $now > $to)) {
			return null;
		}

		$bg = isset($settings['bg']) && in_array($settings['bg'], self::$backgrounds, true) ? $settings['bg'] : 'accent';
		$style = '';

		if ($bg === 'custom') {
			$hex = isset($settings['bg_custom']) ? trim((string)$settings['bg_custom']) : '';
				$hex = preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i', $hex, $m) ? '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3] : $hex; // #rgb shorthand

			if (preg_match('/^#[0-9a-f]{6}$/i', $hex)) {
				$style = '--tb-bg:' . strtolower($hex) . ';--tb-fg:' . self::contrast($hex);
			} else {
				$bg = 'accent'; // invalid colour: back to the theme accent
			}
		}

		$label = isset($l['btn_label']) ? trim((string)$l['btn_label']) : '';
		$btn_href = isset($l['btn_url']) ? Shell::safeUrl($l['btn_url']) : '';
		$href = isset($l['url']) ? Shell::safeUrl($l['url']) : '';
		$closable = !empty($settings['closable']);

		$button = null;

		if ($label !== '' && $btn_href !== '') {
			$button = array(
				'label' => Shell::e($label),
				'href'  => $btn_href,
				'style' => isset($settings['btn_style']) && $settings['btn_style'] === 'outline' ? 'outline' : 'solid'
			);
		}

		return array(
			'id'       => substr(md5($code . '|' . $text . '|' . $label . '|' . $btn_href . '|' . (int)$closable), 0, 10),
			'text'     => Shell::e($text),
			'href'     => $href,
			'button'   => $button,
			'bg'       => $bg,
			'style'    => $style,
			'blank'    => !empty($settings['blank']),
			'closable' => $closable
		);
	}

	/**
	 * Text colour for a #rrggbb background: near-black on light, white on dark (REQ-TBR-005).
	 */
	public static function contrast($hex) {
		$hex = ltrim($hex, '#');
		$lin = array();

		foreach (array(0, 2, 4) as $i) {
			$c = hexdec(substr($hex, $i, 2)) / 255;
			$lin[] = $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
		}

		$lum = 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];

		return $lum > 0.179 ? '#1d1d1f' : '#ffffff';
	}
}
