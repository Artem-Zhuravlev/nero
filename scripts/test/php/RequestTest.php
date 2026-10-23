<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\Migrate\Context;
use Ocs\Test\Support\SqliteDb;

require_once dirname(__DIR__, 3) . '/custom/extensions/ocs_request/upload/system/library/ocs/orderrequest.php';

final class RequestTest extends TestCase
{
    private function cfg(): array
    {
        return [
            'name' => ['enabled' => true, 'required' => true],
            'phone' => ['enabled' => true, 'required' => true],
            'email' => ['enabled' => true, 'required' => false],
            'comment' => ['enabled' => true, 'required' => false],
        ];
    }

    // TEST-QOR-001 - verifies REQ-QOR-003 (server-side validation: required, formats, lengths, whitespace)
    public function test_validate_required_and_formats(): void
    {
        $r = \Ocs\OrderRequest::validate(['name' => ' ', 'phone' => '', 'email' => 'x'], $this->cfg());
        $this->assertSame(['name' => 'required', 'phone' => 'required', 'email' => 'format'], $r['errors']);

        $ok = \Ocs\OrderRequest::validate(['name' => '  Іван   Петренко ', 'phone' => '+38 (050) 123-45-67', 'email' => 'a@b.co', 'comment' => " hi\nthere "], $this->cfg());
        $this->assertSame([], $ok['errors']);
        $this->assertSame('Іван Петренко', $ok['clean']['name']);
        $this->assertSame('+380501234567', $ok['clean']['phone']);
        $this->assertSame("hi\nthere", $ok['clean']['comment']);

        $long = \Ocs\OrderRequest::validate(['name' => 'A', 'phone' => '123'], $this->cfg());
        $this->assertSame('length', $long['errors']['name']);
        $this->assertSame('format', $long['errors']['phone']);
    }

    // verifies REQ-QOR-001 (disabled fields are ignored, optional empty fields pass)
    public function test_disabled_and_optional_fields(): void
    {
        $cfg = $this->cfg();
        $cfg['name']['enabled'] = false;
        $r = \Ocs\OrderRequest::validate(['phone' => '0501234567', 'name' => 'ignored'], $cfg);
        $this->assertSame([], $r['errors']);
        $this->assertSame('', $r['clean']['name']);
        $this->assertSame('', $r['clean']['email']);
    }

    // TEST-QOR-005 - verifies REQ-QOR-031 (honeypot and sliding-window rate limit)
    public function test_honeypot_and_rate_limit(): void
    {
        $spam = \Ocs\OrderRequest::validate(['name' => 'Ab', 'phone' => '0501234567', 'website' => 'http://spam'], $this->cfg());
        $this->assertSame(['spam' => 'spam'], $spam['errors']);

        $stamps = [];
        for ($i = 0; $i < 5; $i++) {
            list($ok, $stamps) = \Ocs\OrderRequest::rateLimit($stamps, 1000 + $i, 5, 60);
            $this->assertTrue($ok);
        }
        list($ok, $stamps) = \Ocs\OrderRequest::rateLimit($stamps, 1010, 5, 60);
        $this->assertFalse($ok, 'sixth request within the window is refused');
        list($ok, $stamps) = \Ocs\OrderRequest::rateLimit($stamps, 1100, 5, 60);
        $this->assertTrue($ok, 'window slides');
        $this->assertCount(1, $stamps);
    }

    // TEST-QOR-003 - verifies REQ-QOR-010, REQ-QOR-012 (pre-order rules: global switch, per-product overrides, never for in-stock products)
    public function test_preorder_rules_and_overrides(): void
    {
        $o = \Ocs\OrderRequest::parseOverrides("42|on|2026-12-01\n7|OFF\nbad|on\n9|maybe\n11|on|31/12/2026");
        $this->assertSame(['mode' => 'on', 'date' => '2026-12-01'], $o[42]);
        $this->assertSame(['mode' => 'off', 'date' => ''], $o[7]);
        $this->assertSame(['mode' => 'on', 'date' => ''], $o[11]);
        $this->assertCount(3, $o);

        $this->assertTrue(\Ocs\OrderRequest::preorderApplies(true, 0, []));
        $this->assertFalse(\Ocs\OrderRequest::preorderApplies(true, 5, []));
        $this->assertFalse(\Ocs\OrderRequest::preorderApplies(false, 0, []));
        $this->assertTrue(\Ocs\OrderRequest::preorderApplies(false, 0, ['mode' => 'on']));
        $this->assertFalse(\Ocs\OrderRequest::preorderApplies(true, 0, ['mode' => 'off']));
        $this->assertFalse(\Ocs\OrderRequest::preorderApplies(false, 3, ['mode' => 'on']), 'in-stock products never get pre-order');
    }

    // verifies REQ-ARCH-063 (entity-encoded settings are not escaped twice)
    public function test_escape_decodes_first(): void
    {
        $this->assertSame('A &amp; B &lt;i&gt;', \Ocs\OrderRequest::e('A &amp; B <i>'));
    }

    // TEST-QOR-002..004 - verifies REQ-EXT-031 (install migrations: tables, disabled by default, events, idempotent)
    public function test_install_migrations(): void
    {
        $db = new SqliteDb();
        $db->createCoreSubset();
        $m = new Context($db, 'oc_', dirname(__DIR__, 3));

        foreach (['ocs_quick_order', 'ocs_preorder', 'ocs_back_in_stock'] as $code) {
            $fn = require dirname(__DIR__, 3) . "/custom/extensions/{$code}/db/001_install.php";
            $fn($m);
            $fn($m);
            $this->assertTrue($m->extension->isRegistered('module', $code), $code);
            $this->assertSame('0', (string) $m->setting->get("module_{$code}", "module_{$code}_status"), "{$code} must default to disabled");
        }
        $this->assertTrue($db->tableExists('oc_ocs_preorder'));
        $this->assertTrue($db->tableExists('oc_ocs_back_in_stock'));
        $this->assertCount(5, $db->pdo->query("SELECT 1 FROM oc_event WHERE code LIKE 'ocs_quick_order_%'")->fetchAll());
        $this->assertCount(2, $db->pdo->query("SELECT 1 FROM oc_event WHERE code LIKE 'ocs_back_in_stock_%'")->fetchAll());
    }
}
