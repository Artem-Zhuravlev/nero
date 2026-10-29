<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\Migrate\Context;
use Ocs\Test\Support\SqliteDb;

final class OosMigrationTest extends TestCase
{
    // TEST-OOS2-001 - verifies REQ-OOS2-003, REQ-OOS2-004 (module enabled, OCMOD installed, idempotent)
    public function test_migration_enables_module_and_installs_ocmod(): void
    {
        $db = new SqliteDb();
        $db->createCoreSubset();
        $m = new Context($db, 'oc_', dirname(__DIR__, 3));
        $m->setting->set('module_ocs_back_in_stock', 'module_ocs_back_in_stock_status', 0);
        $run = function () use ($m): void {
            $fn = require dirname(__DIR__, 3) . '/custom/db/migrations/0013_oos_notify_and_last.php';
            $fn($m);
        };
        $run();
        $run();
        $this->assertSame('1', (string) $m->setting->get('module_ocs_back_in_stock', 'module_ocs_back_in_stock_status'));
        $this->assertCount(1, $db->pdo->query("SELECT 1 FROM oc_modification WHERE code = 'ocs_listing_stock_last'")->fetchAll());
        $this->assertTrue($m->refreshRequired);
    }
}
