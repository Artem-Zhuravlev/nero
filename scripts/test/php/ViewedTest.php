<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\Migrate\Context;
use Ocs\Test\Support\SqliteDb;

require_once dirname(__DIR__, 3) . '/custom/extensions/ocs_viewed/upload/system/library/ocs/viewed.php';

final class ViewedTest extends TestCase
{
    // TEST-VWD-001 - verifies REQ-VWD-001, REQ-VWD-004 (cookie parsing: positive integers, no duplicates, newest first, at most 20)
    public function test_parse_cookie_value(): void
    {
        $this->assertSame([12, 7, 40], \Ocs\Viewed::parse('12,7,40'));
        $this->assertSame([12, 7], \Ocs\Viewed::parse(' 12 ,x,7,12,-3,0,1.5,<b>'), 'junk and duplicates are dropped');
        $this->assertSame([], \Ocs\Viewed::parse(''));
        $this->assertCount(20, \Ocs\Viewed::parse(implode(',', range(1, 40))));
    }

    // TEST-VWD-001 - verifies REQ-VWD-001 (a view moves the product to the front without duplicates; list is cut to 20)
    public function test_push_moves_to_front_and_cuts(): void
    {
        $this->assertSame([3, 1, 2], \Ocs\Viewed::push([1, 2, 3], 3));
        $this->assertSame([9, 1, 2, 3], \Ocs\Viewed::push([1, 2, 3], 9));
        $this->assertSame([1, 2, 3], \Ocs\Viewed::push([1, 2, 3], 0), 'invalid ids are ignored');
        $long = \Ocs\Viewed::push(range(1, 20), 99);
        $this->assertCount(20, $long);
        $this->assertSame(99, $long[0]);
        $this->assertTrue(!in_array(20, $long, true), 'the oldest id falls off');
    }

    // TEST-VWD-001 - verifies REQ-VWD-003 (account history and browser list are merged: account order first, browser-only ids after)
    public function test_merge_account_and_cookie(): void
    {
        $this->assertSame([5, 4, 3, 9, 8], \Ocs\Viewed::merge([5, 4, 3], [3, 9, 5, 8]));
        $this->assertSame([7], \Ocs\Viewed::merge([], [7, 7]));
        $this->assertCount(20, \Ocs\Viewed::merge(range(1, 15), range(10, 40)));
    }

    // TEST-VWD-002 - verifies REQ-VWD-001, REQ-VWD-002 (the current product is left out; the block is limited)
    public function test_visible_excludes_current_and_limits(): void
    {
        $this->assertSame([5, 4, 3], \Ocs\Viewed::visible([5, 40, 4, 3, 2], 40, 3));
        $this->assertSame([5], \Ocs\Viewed::visible([5, 4], 0, 1));
        $this->assertSame([], \Ocs\Viewed::visible([40], 40, 4));
    }

    // TEST-VWD-003 - verifies REQ-VWD-011, REQ-EXT-031 (install: table, disabled by default, record event, idempotent)
    public function test_install_migration(): void
    {
        $db = new SqliteDb();
        $db->createCoreSubset();
        $m = new Context($db, 'oc_', dirname(__DIR__, 3));
        $fn = require dirname(__DIR__, 3) . '/custom/extensions/ocs_viewed/db/001_install.php';
        $fn($m);
        $fn($m);

        $this->assertTrue($db->tableExists('oc_ocs_viewed'));
        $this->assertTrue($m->extension->isRegistered('module', 'ocs_viewed'));
        $this->assertSame('0', (string) $m->setting->get('module_ocs_viewed', 'module_ocs_viewed_status'));
        $this->assertSame('4', (string) $m->setting->get('module_ocs_viewed', 'module_ocs_viewed_limit'));
        $rows = $db->pdo->query("SELECT `trigger` FROM oc_event WHERE code = 'ocs_viewed_record'")->fetchAll();
        $this->assertCount(1, $rows);
        $this->assertSame('catalog/controller/product/product/before', $rows[0]['trigger']);
    }
}
