<?php
declare(strict_types=1);

namespace Ocs\Test;

require_once dirname(__DIR__, 3) . '/custom/extensions/ocs_product_extras/upload/system/library/ocs/video.php';
require_once dirname(__DIR__, 3) . '/custom/extensions/ocs/upload/system/library/ocs/contacts.php';
require_once dirname(__DIR__, 3) . '/custom/extensions/ocs/upload/system/library/ocs/infoblocks.php';

final class VideoInfoTest extends TestCase
{
    // TEST-VID-001 - verifies REQ-VID-001 (every accepted YouTube form gives the id; foreign hosts, junk and wrong lengths give '')
    public function test_parse_video(): void
    {
        $id = 'dQw4w9WgXcQ';
        foreach (['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10', 'http://youtube.com/watch?feature=x&v=dQw4w9WgXcQ', 'https://youtu.be/dQw4w9WgXcQ?si=abc', 'youtu.be/dQw4w9WgXcQ',
                  'https://www.youtube.com/embed/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', 'https://www.youtube.com/shorts/dQw4w9WgXcQ',
                  'https://m.youtube.com/live/dQw4w9WgXcQ', 'dQw4w9WgXcQ', '  dQw4w9WgXcQ  '] as $in) {
            $this->assertSame($id, \Ocs\Video::parse($in), $in);
        }
        foreach (['', 'https://vimeo.com/123456789', 'https://evil.test/watch?v=dQw4w9WgXcQ', 'https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ', 'https://www.youtube.com/watch?v=short',
                  'javascript:alert(1)', 'https://www.youtube.com/watch?v[]=dQw4w9WgXcQ', 'dQw4w9WgXc', 'dQw4w9WgXcQQ', "dQw4w9\nWgXcQ"] as $in) {
            $this->assertSame('', \Ocs\Video::parse($in), $in);
        }
    }

    // TEST-VID-004 - verifies REQ-VID-004 (URLs are built from a validated id only)
    public function test_urls(): void
    {
        $this->assertSame('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', \Ocs\Video::poster('dQw4w9WgXcQ'));
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0', \Ocs\Video::embed('dQw4w9WgXcQ'));
        $this->assertSame('', \Ocs\Video::poster('x" onload="1'));
        $this->assertSame('', \Ocs\Video::embed('../../etc/passwd'));
    }

    // TEST-VID-003 - verifies REQ-VID-003 (rows found at any level, flagged without touching other rows or scalars)
    public function test_flags(): void
    {
        $data = ['product_id' => 5, 'products' => [['product_id' => 1, 'name' => 'a'], ['product_id' => '2']], 'tabs' => [['products' => [['product_id' => 3]]]], 'text' => 'x'];
        $this->assertSame([1, 2, 3], \Ocs\Video::rowIds($data));
        $out = \Ocs\Video::mark($data, [2, 3]);
        $this->assertTrue(empty($out['products'][0]['has_video']) && !empty($out['products'][1]['has_video']) && !empty($out['tabs'][0]['products'][0]['has_video']));
        $this->assertSame(5, $out['product_id']);
        $this->assertSame([], \Ocs\Video::rowIds(['a' => 'b']));
    }

    // TEST-INF-001 - verifies REQ-INF-001 (whitelisted icons, a title or text in some language, scheme-checked link, sorted, max 10)
    public function test_sanitize_info_blocks(): void
    {
        $rows = [
            ['icon' => 'truck', 'title' => ['uk-ua' => ' Доставка ', 'en-gb' => ''], 'text' => ['uk-ua' => "Нова пошта\n1-2 дні", 'en-gb' => ''], 'url' => 'https://shop.test/delivery', 'sort' => '2'],
            ['icon' => 'return', 'title' => ['uk-ua' => '', 'en-gb' => 'Returns'], 'text' => [], 'url' => 'javascript:alert(1)', 'sort' => '1'],
            ['icon' => 'bogus', 'title' => ['uk-ua' => 'x'], 'text' => []],
            ['icon' => 'gift', 'title' => ['uk-ua' => ''], 'text' => ['uk-ua' => '']],
            'junk',
        ];
        $clean = \Ocs\InfoBlocks::sanitize($rows, ['uk-ua', 'en-gb']);
        $this->assertSame(['return', 'truck'], array_column($clean, 'icon'));
        $this->assertSame('', $clean[0]['url'], 'unsafe link dropped, the block stays');
        $this->assertSame('https://shop.test/delivery', $clean[1]['url']);
        $this->assertSame('Доставка', $clean[1]['title']['uk-ua']);
        $many = array_fill(0, 15, ['icon' => 'info', 'title' => ['uk-ua' => 'T'], 'text' => []]);
        $this->assertSame(10, count(\Ocs\InfoBlocks::sanitize($many, ['uk-ua'])));
    }

    // TEST-INF-002 - verifies REQ-INF-002 (view: language fallback, escaping, line breaks, icon symbols, empty = nothing)
    public function test_view_info_blocks(): void
    {
        $rows = [
            ['icon' => 'truck', 'title' => ['uk-ua' => 'Доставка <b>', 'en-gb' => ''], 'text' => ['uk-ua' => "Рядок 1\nРядок 2", 'en-gb' => ''], 'url' => 'https://shop.test/d'],
            ['icon' => 'card', 'title' => ['uk-ua' => '', 'en-gb' => ''], 'text' => ['uk-ua' => '', 'en-gb' => '']],
            ['icon' => 'shield', 'title' => ['uk-ua' => 'Гарантія', 'en-gb' => 'Warranty'], 'text' => [], 'url' => 'javascript:1'],
        ];
        $v = \Ocs\InfoBlocks::view($rows, 'en-gb', 'uk-ua');
        $this->assertSame(2, count($v), 'empty block skipped');
        $this->assertSame('i-truck', $v[0]['icon']);
        $this->assertSame('Доставка &lt;b&gt;', $v[0]['title'], 'fallback to the default language, escaped');
        $this->assertSame('Рядок 1<br>Рядок 2', $v[0]['text']);
        $this->assertSame('Warranty', $v[1]['title']);
        $this->assertSame('', $v[1]['href']);
        $this->assertSame([], \Ocs\InfoBlocks::view(null, 'uk-ua', 'uk-ua'));
        $sprite = file_get_contents(dirname(__DIR__, 3) . '/custom/theme/ocs/template/ocs/sprite.twig');
        foreach (\Ocs\InfoBlocks::$icons as $name => $symbol) {
            $this->assertTrue(strpos($sprite, 'id="' . $symbol . '"') !== false, "sprite lacks $symbol");
        }
    }

    // TEST-INF-101 - verifies REQ-INF-102, REQ-INF-103, REQ-INF-104 (custom icon: safe path kept, unsafe dropped, image alone makes a row valid, view exposes it)
    public function test_custom_icon_image(): void
    {
        $this->assertSame('catalog/icons/dhl.svg', \Ocs\InfoBlocks::image('/catalog/icons/dhl.svg'));
        $this->assertSame('', \Ocs\InfoBlocks::image('../config.php'));
        $this->assertSame('', \Ocs\InfoBlocks::image('catalog/a/../../x.png'));
        $this->assertSame('', \Ocs\InfoBlocks::image('catalog/shell.php'));
        $rows = [
            ['icon' => 'truck', 'image' => 'catalog/dhl.png', 'title' => ['uk-ua' => 'DHL'], 'text' => []],
            ['image' => 'catalog/only.png', 'title' => ['uk-ua' => 'Only image'], 'text' => []],
            ['icon' => 'gift', 'image' => 'catalog/bad.php', 'title' => ['uk-ua' => 'Bad'], 'text' => []],
            ['icon' => 'bogus', 'image' => '', 'title' => ['uk-ua' => 'x'], 'text' => []],
        ];
        $clean = \Ocs\InfoBlocks::sanitize($rows, ['uk-ua']);
        $this->assertSame(3, count($clean));
        $this->assertSame(['catalog/dhl.png', 'catalog/only.png', ''], array_column($clean, 'image'));
        $this->assertSame('info', $clean[1]['icon']);
        $v = \Ocs\InfoBlocks::view($clean, 'uk-ua', 'uk-ua');
        $this->assertSame('catalog/dhl.png', $v[0]['image']);
        $this->assertSame('', $v[2]['image']);
    }
}
