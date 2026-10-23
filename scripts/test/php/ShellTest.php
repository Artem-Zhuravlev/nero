<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\Migrate\Context;
use Ocs\Test\Support\SqliteDb;

require_once dirname(__DIR__, 3) . '/custom/extensions/ocs_shell/upload/system/library/ocs/shell.php';

final class ShellTest extends TestCase
{
    // TEST-SHL-001 - verifies REQ-SHL-011 (links/phones parsed, text escaped, unsafe URLs dropped)
    public function test_parsers_escape_and_filter_urls(): void
    {
        $links = \Ocs\Shell::parseLinks("Blog|/blog\nEvil|javascript:alert(1)\n<b>Plain</b>\nTel|tel:+380441234567");
        $this->assertCount(4, $links);
        $this->assertSame('/blog', $links[0]['href']);
        $this->assertSame('', $links[1]['href']);
        $this->assertSame('&lt;b&gt;Plain&lt;/b&gt;', $links[2]['text']);
        $this->assertSame('tel:+380441234567', $links[3]['href']);
        $this->assertSame('', \Ocs\Shell::safeUrl('//evil.example'));
        $this->assertSame('https://t.me/x?a=1&amp;b=2', \Ocs\Shell::safeUrl('https://t.me/x?a=1&b=2'));

        $phones = \Ocs\Shell::parsePhones("044 333 64 18\n+38 (0 800) 507 800");
        $this->assertSame('tel:0443336418', $phones[0]['href']);
        $this->assertSame('tel:+380800507800', $phones[1]['href']);
    }

    // TEST-SHL-002 - verifies REQ-SHL-001 (announcement by language and date range)
    public function test_announcement_respects_language_and_dates(): void
    {
        $s = ['l' => ['uk-ua' => ['announcement_text' => 'Hello <i>', 'announcement_url' => '/sale']], 'announcement_from' => '2026-10-01', 'announcement_to' => '2026-10-31'];
        $in = strtotime('2026-10-15 12:00:00');
        $a = \Ocs\Shell::announcement($s, 'uk-ua', $in);
        $this->assertSame('Hello &lt;i&gt;', $a['text']);
        $this->assertSame('/sale', $a['href']);
        $this->assertSame(10, strlen($a['id']));
        $this->assertSame(null, \Ocs\Shell::announcement($s, 'en-gb', $in));
        $this->assertSame(null, \Ocs\Shell::announcement($s, 'uk-ua', strtotime('2026-09-30 23:59:59')));
        $this->assertSame(null, \Ocs\Shell::announcement($s, 'uk-ua', strtotime('2026-11-01 00:00:00')));
        $this->assertTrue(\Ocs\Shell::announcement($s, 'uk-ua', strtotime('2026-10-31 23:59:00')) !== null);
    }

    // TEST-SHL-003 - verifies REQ-SHL-010 (footer groups: title line + links, blank line between groups)
    public function test_footer_groups_and_messengers(): void
    {
        $g = \Ocs\Shell::parseGroups("Customers\nDelivery|/delivery\nReturns|/returns\n\nStores\nKyiv|/kyiv");
        $this->assertCount(2, $g);
        $this->assertSame('Customers', $g[0]['title']);
        $this->assertCount(2, $g[0]['links']);
        $this->assertSame('/kyiv', $g[1]['links'][0]['href']);

        $m = \Ocs\Shell::messengers(['telegram' => 'https://t.me/shop', 'viber' => 'javascript:x', 'messenger' => '']);
        $this->assertCount(1, $m);
        $this->assertSame('telegram', $m[0]['key']);
    }

    // TEST-SHL-001 - verifies REQ-EXT-031, REQ-SHL-041 (install migration: disabled by default, events, idempotent)
    public function test_install_migration_defaults_to_disabled_and_registers_events(): void
    {
        $db = new SqliteDb();
        $db->createCoreSubset();
        $m = new Context($db, 'oc_', dirname(__DIR__, 3));
        $run = function () use ($m): void {
            $fn = require dirname(__DIR__, 3) . '/custom/extensions/ocs_shell/db/001_install.php';
            $fn($m);
        };
        $run();
        $run();
        $this->assertSame('0', (string) $m->setting->get('module_ocs_shell', 'module_ocs_shell_status'));
        $this->assertTrue($m->extension->isRegistered('module', 'ocs_shell'));
        $this->assertCount(2, $db->pdo->query("SELECT 1 FROM oc_event WHERE code LIKE 'ocs_shell_%'")->fetchAll());
    }
}
