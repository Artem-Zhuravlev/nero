<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\Migrate\Context;
use Ocs\Test\Support\SqliteDb;

require_once dirname(__DIR__, 3) . '/custom/extensions/ocs_request/upload/system/library/ocs/orderrequest.php';
require_once dirname(__DIR__, 3) . '/custom/extensions/ocs_request/upload/system/library/ocs/stock.php';
require_once dirname(__DIR__, 3) . '/custom/extensions/ocs_back_in_stock/upload/system/library/ocs/backinstock.php';

final class StockRulesTest extends TestCase
{
    // TEST-OPT-001 - verifies REQ-OPT-001 (core hides a value only with "subtract stock" on and no quantity)
    public function test_option_out(): void
    {
        $this->assertTrue(\Ocs\Stock::optionOut(1, 0));
        $this->assertTrue(\Ocs\Stock::optionOut('1', '-2'));
        $this->assertTrue(!\Ocs\Stock::optionOut(1, 3));
        $this->assertTrue(!\Ocs\Stock::optionOut(0, 0), 'a value that does not subtract stock is always available');
    }

    // TEST-OPT-001 - verifies REQ-OPT-001 (the posted option[] array -> the chosen value ids; text options and junk are ignored)
    public function test_selected_value_ids(): void
    {
        $this->assertSame([12, 5, 7], \Ocs\Stock::selectedValueIds([224 => '12', 225 => ['5', '7'], 226 => 'free text', 227 => ['x' => "9' OR 1"]]));
        $this->assertSame([], \Ocs\Stock::selectedValueIds([]));
        $this->assertSame([4], \Ocs\Stock::selectedValueIds([1 => ['4', '4']]));
    }

    // TEST-OPT-001 - verifies REQ-OPT-001 (an out-of-stock selection needs the request button; the product level wins)
    public function test_selection_needs_request(): void
    {
        $out = [12 => true];
        $this->assertTrue(\Ocs\Stock::selectionNeedsRequest(false, [13, 12], $out));
        $this->assertTrue(!\Ocs\Stock::selectionNeedsRequest(false, [13], $out));
        $this->assertTrue(\Ocs\Stock::selectionNeedsRequest(true, [], $out));
        $this->assertTrue(!\Ocs\Stock::selectionNeedsRequest(false, [], []));
    }

    // TEST-OPT-001 - verifies REQ-OPT-001 (per-product "off" disables the option rule, "on" and the global switch enable it)
    public function test_option_rule_and_preorder_applies(): void
    {
        $this->assertTrue(\Ocs\Stock::optionRuleOn(true, []));
        $this->assertTrue(!\Ocs\Stock::optionRuleOn(false, []));
        $this->assertTrue(\Ocs\Stock::optionRuleOn(false, ['mode' => 'on']));
        $this->assertTrue(!\Ocs\Stock::optionRuleOn(true, ['mode' => 'off']));

        $out = [12 => true];
        $this->assertTrue(\Ocs\Stock::preorderAppliesFor(true, 0, [], [], []), 'product out of stock');
        $this->assertTrue(\Ocs\Stock::preorderAppliesFor(true, 50, [], [12], $out), 'product in stock, option out');
        $this->assertTrue(!\Ocs\Stock::preorderAppliesFor(true, 50, [], [13], $out), 'product in stock, option in stock');
        $this->assertTrue(!\Ocs\Stock::preorderAppliesFor(true, 50, ['mode' => 'off'], [12], $out), 'switched off for the product');
        $this->assertTrue(!\Ocs\Stock::preorderAppliesFor(false, 50, [], [12], $out), 'global switch off');
        $this->assertTrue(\Ocs\Stock::preorderAppliesFor(false, 50, ['mode' => 'on'], [12], $out), 'switched on for the product');
    }

    // TEST-OPT-002 - verifies REQ-OPT-002 (a subscription is ready only when the product and every option value of it are in stock)
    public function test_back_in_stock_ready(): void
    {
        $out = [12 => true];
        $this->assertTrue(\Ocs\BackInStock::ready(5, '', $out));
        $this->assertTrue(!\Ocs\BackInStock::ready(0, '', $out));
        $this->assertTrue(!\Ocs\BackInStock::ready(5, '12', $out), 'the subscribed value is still out');
        $this->assertTrue(\Ocs\BackInStock::ready(5, '13,14', $out));
        $this->assertTrue(!\Ocs\BackInStock::ready(5, '13,12', $out));
        $this->assertTrue(\Ocs\BackInStock::ready(5, '12', []), 'the value came back');
    }

    // TEST-OPT-002 - verifies REQ-OPT-002 (cron key: long enough, compared exactly)
    public function test_cron_key(): void
    {
        $key = str_repeat('a1', 16);
        $this->assertTrue(\Ocs\BackInStock::keyValid($key, $key));
        $this->assertTrue(!\Ocs\BackInStock::keyValid($key, ''));
        $this->assertTrue(!\Ocs\BackInStock::keyValid($key, $key . 'x'));
        $this->assertTrue(!\Ocs\BackInStock::keyValid('', ''), 'an empty configured key never opens the endpoint');
        $this->assertTrue(!\Ocs\BackInStock::keyValid('short', 'short'));
        $this->assertTrue(!\Ocs\BackInStock::keyValid($key, null));
    }

    // TEST-QVW-001, TEST-OPT-002 - verifies REQ-QVW-003, REQ-OPT-001, REQ-OPT-002, REQ-EXT-031 (new migrations: off by default, events, cron key once)
    public function test_migrations(): void
    {
        $db = new SqliteDb();
        $db->createCoreSubset();
        $m = new Context($db, 'oc_', dirname(__DIR__, 3));
        $root = dirname(__DIR__, 3) . '/custom/extensions';

        foreach (['ocs_quick_view/db/001_install.php', 'ocs_preorder/db/001_install.php', 'ocs_preorder/db/002_option_stock_event.php', 'ocs_back_in_stock/db/001_install.php', 'ocs_back_in_stock/db/002_option_stock_event.php', 'ocs_back_in_stock/db/003_cron_key.php'] as $file) {
            $fn = require $root . '/' . $file;
            $fn($m);
            $fn($m);
        }

        $this->assertTrue($m->extension->isRegistered('module', 'ocs_quick_view'));
        $this->assertSame('0', (string) $m->setting->get('module_ocs_quick_view', 'module_ocs_quick_view_status'));
        $this->assertCount(1, $db->pdo->query("SELECT code FROM oc_event WHERE code = 'ocs_quick_view_header'")->fetchAll());
        $this->assertCount(1, $db->pdo->query("SELECT code FROM oc_event WHERE code = 'ocs_preorder_options'")->fetchAll());
        $this->assertCount(1, $db->pdo->query("SELECT code FROM oc_event WHERE code = 'ocs_back_in_stock_options'")->fetchAll());

        $key = (string) $m->setting->get('module_ocs_back_in_stock', 'module_ocs_back_in_stock_cron_key');
        $this->assertTrue(strlen($key) >= 16, 'the cron key is generated');

        $again = require $root . '/ocs_back_in_stock/db/003_cron_key.php';
        $again($m);
        $this->assertSame($key, (string) $m->setting->get('module_ocs_back_in_stock', 'module_ocs_back_in_stock_cron_key'), 'a second run keeps the key');
    }
}
