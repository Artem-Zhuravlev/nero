<?php
declare(strict_types=1);

namespace Ocs\Test;

require_once dirname(__DIR__, 3) . '/custom/extensions/ocs_bookmarks/upload/system/library/ocs/bookmarks.php';
require_once dirname(__DIR__, 3) . '/custom/extensions/ocs/upload/system/library/ocs/display.php';

final class BookmarksDisplayTest extends TestCase
{
    // TEST-BKM-001 - verifies REQ-BKM-001, REQ-BKM-003 (cookie ids: integers only, unique, capped to the newest, garbage ignored)
    public function test_parse_cookie(): void
    {
        $this->assertSame([12, 15], \Ocs\Bookmarks::parse('12,15,15,abc,-3,0,1.5,', 100));
        $this->assertSame([], \Ocs\Bookmarks::parse('', 100));
        $this->assertSame([], \Ocs\Bookmarks::parse(['x'], 100));
        $this->assertSame([], \Ocs\Bookmarks::parse(str_repeat('1,', 1500), 100));
        $this->assertSame([3, 4, 5, 6], \Ocs\Bookmarks::parse('1,2,3,4,5,6', 4));
        $this->assertSame('3,4', \Ocs\Bookmarks::serialize([3, '4']));
    }

    // TEST-BKM-001 - verifies REQ-BKM-001 (add moves to the end, cap keeps the newest, remove drops)
    public function test_change(): void
    {
        $this->assertSame([1, 2, 9], \Ocs\Bookmarks::change([1, 9, 2], 'add', 9, 4));
        $this->assertSame([2, 3, 4, 5], \Ocs\Bookmarks::change([1, 2, 3, 4], 'add', 5, 4));
        $this->assertSame([1, 3], \Ocs\Bookmarks::change([1, 2, 3], 'remove', 2, 4));
        $this->assertSame([1], \Ocs\Bookmarks::change([1], 'add', 0, 4));
    }

    // TEST-VIS-050 - verifies REQ-VIS-050 (an unset switch means on)
    public function test_switch_values(): void
    {
        $this->assertTrue(\Ocs\Display::on(null));
        $this->assertTrue(\Ocs\Display::on(''));
        $this->assertTrue(\Ocs\Display::on('1'));
        $this->assertTrue(!\Ocs\Display::on('0'));
        $this->assertTrue(!\Ocs\Display::on(0));
    }

    // TEST-VIS-051 - verifies REQ-VIS-051 (switched-off stock / sku are removed from the page, product rows and nested lists; flags are set)
    public function test_apply_strips_data(): void
    {
        $data = [
            'model' => 'A-1', 'stock' => 'In stock', 'ocs_stock' => ['state' => 'in', 'label' => 'x'], 'heading_title' => 'P',
            'products' => [
                ['product_id' => 1, 'name' => 'n', 'model' => 'M1', 'ocs_stock' => ['state' => 'out'], 'quantity' => 0, 'stock' => true],
                7 => ['product_id' => 2, 'model' => 'M2', 'availability' => 'Out'],
            ],
            'name' => 'keep',
        ];

        $off = \Ocs\Display::apply($data, false, false);

        $this->assertTrue(!isset($off['model']) && !isset($off['stock']) && !isset($off['ocs_stock']));
        $this->assertTrue(!isset($off['products'][0]['model']) && !isset($off['products'][0]['ocs_stock']));
        $this->assertTrue($off['products'][0]['stock'] === true, 'the cart flag "stock" is a boolean and stays');
        $this->assertTrue(!isset($off['products'][7]['model']) && !isset($off['products'][7]['availability']));
        $this->assertSame('keep', $off['name']);
        $this->assertTrue($off['ocs_show_stock'] === false && $off['ocs_show_sku'] === false);

        $stock_only = \Ocs\Display::apply($data, true, false);
        $this->assertTrue(!isset($stock_only['model']) && isset($stock_only['ocs_stock']) && $stock_only['ocs_show_stock'] === true);

        $on = \Ocs\Display::apply($data, true, true);
        $this->assertSame('A-1', $on['model']);
        $this->assertSame('M1', $on['products'][0]['model']);
    }
}
