<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\Migrate\Context;
use Ocs\Test\Support\SqliteDb;

final class AjaxCartTest extends TestCase
{
    // TEST-CRT-004 - verifies REQ-CRT-021, REQ-EXT-031 (module disabled by default, six events after the listing events, idempotent)
    public function test_install_migration(): void
    {
        $db = new SqliteDb();
        $db->createCoreSubset();
        $m = new Context($db, 'oc_', dirname(__DIR__, 3));
        $fn = require dirname(__DIR__, 3) . '/custom/extensions/ocs_ajax_cart/db/001_install.php';
        $fn($m);
        $fn($m);

        $this->assertTrue($m->extension->isRegistered('module', 'ocs_ajax_cart'));
        $this->assertSame('0', (string) $m->setting->get('module_ocs_ajax_cart', 'module_ocs_ajax_cart_status'));
        $this->assertSame('5', (string) $m->setting->get('module_ocs_ajax_cart', 'module_ocs_ajax_cart_low_stock'));
        $rows = $db->pdo->query("SELECT sort_order FROM oc_event WHERE code LIKE 'ocs_ajax_cart_%'")->fetchAll();
        $this->assertCount(6, $rows);
        foreach ($rows as $row) {
            $this->assertSame('3', (string) $row['sort_order']);
        }
    }
}
