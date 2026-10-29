<?php
declare(strict_types=1);

namespace Ocs\Test;

require_once dirname(__DIR__, 3) . '/custom/extensions/ocs/upload/system/library/ocs/fonts.php';

final class FontsTest extends TestCase
{
    private function dir(): string
    {
        return dirname(__DIR__, 3) . '/custom/theme/ocs/fonts/';
    }

    // TEST-FCH-001 - verifies REQ-FCH-001, REQ-FCH-004 (white-list, fallback, fonts without files unavailable)
    public function test_resolve(): void
    {
        $d = $this->dir();
        $this->assertSame('inter', \Ocs\Fonts::resolve('inter', $d));
        $this->assertSame('montserrat', \Ocs\Fonts::resolve('nope', $d));
        $this->assertSame('montserrat', \Ocs\Fonts::resolve(null, $d));
        $this->assertSame('montserrat', \Ocs\Fonts::resolve('../../etc', $d));
        $o = \Ocs\Fonts::options($d);
        $this->assertSame(9, count($o));
        $this->assertTrue($o['onest']['available']);
        if (!is_file($d . 'gilroy-latin-400.woff2')) {
            $this->assertFalse($o['gilroy']['available']);
            $this->assertSame('montserrat', \Ocs\Fonts::resolve('gilroy', $d));
        }
    }

    // TEST-FCH-002 - verifies REQ-FCH-002, REQ-FCH-003, REQ-FCH-011 (generated css: only the chosen family, swap, fallback, no external hosts)
    public function test_render(): void
    {
        $d = $this->dir();
        $r = \Ocs\Fonts::render('manrope', $d);
        $this->assertTrue(strpos($r['css'], 'font-family:"Manrope"') !== false);
        $this->assertTrue(strpos($r['css'], 'font-display:swap') !== false);
        $this->assertTrue(strpos($r['css'], '"Manrope Fallback"') !== false);
        $this->assertTrue(strpos($r['css'], '--font:"Manrope"') !== false);
        $this->assertTrue(strpos($r['css'], 'montserrat') === false);
        $this->assertTrue(strpos($r['css'] . $r['preload'], 'http') === false);
        $this->assertTrue(strpos($r['preload'], 'manrope-cyrillic-400.woff2') !== false);

        $def = \Ocs\Fonts::render('montserrat', $d);
        $this->assertSame('', $def['css']);
        $this->assertTrue(strpos($def['preload'], 'montserrat-cyrillic-700.woff2') !== false);
    }
}
