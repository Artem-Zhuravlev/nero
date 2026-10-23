<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\Migrate\Context;
use Ocs\Test\Support\SqliteDb;

require_once dirname(__DIR__, 3) . '/custom/extensions/ocs_city/upload/system/library/ocs/city.php';

final class CityTest extends TestCase
{
    // TEST-CTY-001 - verifies REQ-CTY-001, REQ-CTY-040 (cookie city wins when active; else default; else first; else 0)
    public function test_current_city(): void
    {
        $this->assertSame(3, \Ocs\City::current('3', [2, 3], 2));
        $this->assertSame(2, \Ocs\City::current('9', [2, 3], 2), 'unknown cookie city falls back to the default');
        $this->assertSame(2, \Ocs\City::current('abc', [2, 3], 0), 'junk falls back to the first city');
        $this->assertSame(0, \Ocs\City::current('3', [], 0));
        $this->assertSame(2, \Ocs\City::current(null, [2, 3], 2));
    }

    // TEST-CTY-003 - verifies REQ-CTY-010 ("tomorrow" before the cut-off, days range after it, nothing without data)
    public function test_eta(): void
    {
        $rule = ['days_min' => 1, 'days_max' => 3, 'cutoff' => '16:00'];
        $this->assertSame('tomorrow', \Ocs\City::eta($rule, '15:59')['type']);
        $this->assertSame(['type' => 'days', 'min' => 1, 'max' => 3], \Ocs\City::eta($rule, '16:00'));
        $this->assertSame('tomorrow', \Ocs\City::eta(['cutoff' => '16:00'], '09:00')['type']);
        $this->assertSame('none', \Ocs\City::eta(['cutoff' => '16:00'], '18:00')['type']);
        $this->assertSame('none', \Ocs\City::eta([], '10:00')['type']);
        $this->assertSame(['type' => 'days', 'min' => 2, 'max' => 2], \Ocs\City::eta(['days_min' => 2, 'days_max' => 1], '10:00'), 'max never below min');
        $this->assertTrue(\Ocs\City::validCutoff('') && \Ocs\City::validCutoff('23:59') && !\Ocs\City::validCutoff('24:00') && !\Ocs\City::validCutoff('9:00'));
    }

    // TEST-CTY-003 - verifies REQ-CTY-010 (free delivery by price or threshold)
    public function test_price(): void
    {
        $this->assertTrue(\Ocs\City::price(['price' => 0], 10)['free']);
        $this->assertTrue(\Ocs\City::price(['price' => 100, 'free_from' => 1000], 1000)['free']);
        $paid = \Ocs\City::price(['price' => 100, 'free_from' => 1000], 999);
        $this->assertTrue(!$paid['free']);
        $this->assertSame(100.0, $paid['price']);
        $this->assertSame(1000.0, $paid['free_from']);
        $this->assertTrue(!\Ocs\City::price(['price' => 100, 'free_from' => 0], 99999)['free'], 'no threshold = never free');
    }

    // TEST-CTY-006 - verifies REQ-CTY-030, REQ-CTY-041 (second factor: e-mail or phone must match; short or empty input never does)
    public function test_tracking_second_factor(): void
    {
        $order = ['email' => 'Buyer@Example.com', 'telephone' => '+380501234567'];
        $this->assertTrue(\Ocs\City::trackingMatches($order, 'buyer@example.com'));
        $this->assertTrue(\Ocs\City::trackingMatches($order, '050 123-45-67'));
        $this->assertTrue(\Ocs\City::trackingMatches($order, '+38 (050) 123 4567'));
        $this->assertTrue(!\Ocs\City::trackingMatches($order, 'other@example.com'));
        $this->assertTrue(!\Ocs\City::trackingMatches($order, '0501234'), 'fewer than 9 digits never match');
        $this->assertTrue(!\Ocs\City::trackingMatches($order, ''));
        $this->assertTrue(!\Ocs\City::trackingMatches([], 'buyer@example.com'));
        $this->assertTrue(!\Ocs\City::trackingMatches(['email' => '', 'telephone' => ''], '@'), 'an order without e-mail cannot be matched by "@"');
        $this->assertTrue(!\Ocs\City::trackingMatches(['email' => 'a@b.com', 'telephone' => ''], '000000000'));
    }

    // TEST-CTY-004 - verifies REQ-CTY-020 (map link http(s) only; coordinates in range)
    public function test_links_and_coordinates(): void
    {
        $this->assertSame('https://maps.example.com/x?a=1', \Ocs\City::safeLink(' https://maps.example.com/x?a=1 '));
        $this->assertSame('', \Ocs\City::safeLink('javascript:alert(1)'));
        $this->assertSame('', \Ocs\City::safeLink('https://x.com/"onmouseover='));
        $this->assertSame('50.4501', \Ocs\City::coordinate('50.4501', 90));
        $this->assertSame('', \Ocs\City::coordinate('91', 90));
        $this->assertSame('', \Ocs\City::coordinate('abc', 180));
        $this->assertSame('', \Ocs\City::coordinate('', 180));
    }

    // TEST-CTY-001, TEST-CTY-003 - verifies REQ-CTY-001, REQ-CTY-010 (city form: names for every language, rules checked per method)
    public function test_clean_city(): void
    {
        $ok = \Ocs\City::cleanCity(['name' => [1 => 'Kyiv', 2 => 'Київ'], 'is_default' => '1', 'rule' => ['courier' => ['status' => '1', 'price' => '100', 'days_min' => '1', 'days_max' => '2', 'cutoff' => '16:00', 'notes' => [1 => 'x']]]], [1, 2]);
        $this->assertSame([], $ok['errors']);
        $this->assertSame(1, $ok['data']['is_default']);
        $this->assertSame(['courier'], array_keys($ok['data']['rules']));
        $this->assertSame(100.0, $ok['data']['rules']['courier']['price']);

        $this->assertSame('name', \Ocs\City::cleanCity(['name' => [1 => 'Kyiv']], [1, 2])['errors']['name']);
        $this->assertSame('cutoff', \Ocs\City::cleanCity(['name' => [1 => 'A'], 'rule' => ['post' => ['status' => '1', 'cutoff' => '99:99']]], [1])['errors']['rule_post']);
        $this->assertSame('days', \Ocs\City::cleanCity(['name' => [1 => 'A'], 'rule' => ['post' => ['status' => '1', 'days_min' => '5', 'days_max' => '2']]], [1])['errors']['rule_post']);
        $this->assertSame([], \Ocs\City::cleanCity(['name' => [1 => 'A'], 'rule' => ['bogus' => ['status' => '1']]], [1])['data']['rules'], 'unknown methods are ignored');
    }

    // TEST-CTY-004 - verifies REQ-CTY-020 (store form: city must exist, safe map link, valid phone and coordinates)
    public function test_clean_store(): void
    {
        $base = ['city_id' => '2', 'desc' => [1 => ['name' => 'Centre', 'address' => 'Main 1']], 'phone' => '+380 44 123-45-67', 'map_link' => 'https://m.example.com/x', 'latitude' => '50.45', 'longitude' => '30.52'];
        $this->assertSame([], \Ocs\City::cleanStore($base, [1], [2, 3])['errors']);
        $this->assertSame('city', \Ocs\City::cleanStore(['city_id' => '9'] + $base, [1], [2, 3])['errors']['city']);
        $this->assertSame('map', \Ocs\City::cleanStore(['map_link' => 'javascript:alert(1)'] + $base, [1], [2])['errors']['map']);
        $this->assertSame('phone', \Ocs\City::cleanStore(['phone' => '<b>'] + $base, [1], [2])['errors']['phone']);
        $this->assertSame('coords', \Ocs\City::cleanStore(['latitude' => '123'] + $base, [1], [2])['errors']['coords']);
        $this->assertSame('address', \Ocs\City::cleanStore(['desc' => [1 => ['name' => 'N', 'address' => '']]] + $base, [1], [2])['errors']['address']);
    }

    // TEST-CTY-001 - verifies REQ-CTY-001, REQ-EXT-031 (install: tables, off by default, events, idempotent)
    public function test_install_migration(): void
    {
        $db = new SqliteDb();
        $db->createCoreSubset();
        $m = new Context($db, 'oc_', dirname(__DIR__, 3));
        $fn = require dirname(__DIR__, 3) . '/custom/extensions/ocs_city/db/001_install.php';
        $fn($m);
        $fn($m);

        foreach (['ocs_city', 'ocs_city_description', 'ocs_city_delivery', 'ocs_city_delivery_description', 'ocs_store', 'ocs_store_description', 'ocs_store_stock'] as $table) {
            $this->assertTrue($db->tableExists('oc_' . $table), $table);
        }

        $this->assertTrue($m->extension->isRegistered('module', 'ocs_city'));
        $this->assertSame('0', (string) $m->setting->get('module_ocs_city', 'module_ocs_city_status'));
        $this->assertCount(2, $db->pdo->query("SELECT code FROM oc_event WHERE code LIKE 'ocs_city_%'")->fetchAll());
    }
}
