<?php
// Extension: ocs (theme). Font choice of the theme (theme_ocs_font): catalogue, white-list resolve, generated @font-face and preload.
// Files: <fonts dir>/<key>-<latin|cyrillic>-<weight>.woff2. A font without files is not selectable and never reaches the page.
// Montserrat is declared statically in stylesheet.css (SPEC-102), so for it the generated CSS is empty. Pure, no registry access.
// Owner: SPEC-121 (REQ-FCH-001..004).

namespace Ocs;

class Fonts {
	const DEFAULT_KEY = 'montserrat';

	public static $weights = array(400, 500, 600, 700, 800);

	// key => name, CSS family, size-adjust % of the Arial fallback (keeps the layout still while the font loads)
	public static $catalog = array(
		'montserrat'   => array('name' => 'Montserrat',   'family' => 'Montserrat',   'adjust' => 112),
		'gilroy'       => array('name' => 'Gilroy',       'family' => 'Gilroy',       'adjust' => 106),
		'proxima-nova' => array('name' => 'Proxima Nova', 'family' => 'Proxima Nova', 'adjust' => 105),
		'geometria'    => array('name' => 'Geometria',    'family' => 'Geometria',    'adjust' => 108),
		'inter'        => array('name' => 'Inter',        'family' => 'Inter',        'adjust' => 107),
		'manrope'      => array('name' => 'Manrope',      'family' => 'Manrope',      'adjust' => 106),
		'onest'        => array('name' => 'Onest',        'family' => 'Onest',        'adjust' => 105),
		'golos-text'   => array('name' => 'Golos Text',   'family' => 'Golos Text',   'adjust' => 105),
		'nunito-sans'  => array('name' => 'Nunito Sans',  'family' => 'Nunito Sans',  'adjust' => 104)
	);

	private static $ranges = array(
		'cyrillic' => 'U+0301,U+0400-045F,U+0490-0491,U+04B0-04B1,U+2116',
		'latin'    => 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD'
	);

	/**
	 * True when the files that make the font usable exist: regular and bold in both scripts. Montserrat ships with the theme.
	 */
	public static function available($key, $dir) {
		if (!isset(self::$catalog[$key])) {
			return false;
		}

		if ($key === self::DEFAULT_KEY) {
			return true;
		}

		foreach (array('latin', 'cyrillic') as $script) {
			foreach (array(400, 700) as $weight) {
				if (!is_file(rtrim($dir, '/\\') . '/' . $key . '-' . $script . '-' . $weight . '.woff2')) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * White-list: anything unknown or without files becomes the default.
	 */
	public static function resolve($key, $dir) {
		$key = is_string($key) ? $key : '';

		return self::available($key, $dir) ? $key : self::DEFAULT_KEY;
	}

	/**
	 * @return array key => array(name, available) for the admin select
	 */
	public static function options($dir) {
		$list = array();

		foreach (self::$catalog as $key => $font) {
			$list[$key] = array('name' => $font['name'], 'available' => self::available($key, $dir));
		}

		return $list;
	}

	/**
	 * @param string $base URL path of the fonts directory as the page sees it
	 * @return array css (style element content, '' for the default font), preload (link tags)
	 */
	public static function render($key, $dir, $base = 'catalog/view/theme/ocs/fonts/') {
		$key = self::resolve($key, $dir);
		$out = array('key' => $key, 'css' => '', 'preload' => '');

		if ($key !== self::DEFAULT_KEY) {
			$font = self::$catalog[$key];
			$dir = rtrim($dir, '/\\') . '/';

			foreach (self::$weights as $weight) {
				foreach (array('cyrillic', 'latin') as $script) {
					if (is_file($dir . $key . '-' . $script . '-' . $weight . '.woff2')) {
						$out['css'] .= '@font-face{font-family:"' . $font['family'] . '";font-style:normal;font-weight:' . $weight . ';font-display:swap;src:url("' . $base . $key . '-' . $script . '-' . $weight . '.woff2") format("woff2");unicode-range:' . self::$ranges[$script] . '}';
					}
				}
			}

			$out['css'] .= '@font-face{font-family:"' . $font['family'] . ' Fallback";src:local("Arial"),local("Helvetica");size-adjust:' . $font['adjust'] . '%;ascent-override:90%;descent-override:22%;line-gap-override:0%}';
			$out['css'] .= ':root{--font:"' . $font['family'] . '","' . $font['family'] . ' Fallback",system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}';
		}

		foreach (array(400, 700) as $weight) {
			$out['preload'] .= '<link rel="preload" href="' . $base . $key . '-cyrillic-' . $weight . '.woff2" as="font" type="font/woff2" crossorigin>' . "\n";
		}

		return $out;
	}
}
