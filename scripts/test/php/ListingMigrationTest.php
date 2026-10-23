<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\Migrate\Context;
use Ocs\Test\Support\SqliteDb;

final class ListingMigrationTest extends TestCase
{
    // TEST-LST-002 - verifies REQ-LST-001, REQ-LST-021 (install: switch on, OCMOD installed, four view events, idempotent)
    public function test_install_registers_ocmod_events_and_switch(): void
    {
        $db = new SqliteDb();
        $db->createCoreSubset();
        $m = new Context($db, 'oc_', dirname(__DIR__, 3));
        $run = function () use ($m): void {
            $fn = require dirname(__DIR__, 3) . '/custom/extensions/ocs_listing/db/001_install.php';
            $fn($m);
        };
        $run();
        $run();
        $this->assertSame('1', (string) $m->setting->get('library_ocs_listing', 'library_ocs_listing_status'));
        $this->assertCount(4, $db->pdo->query("SELECT 1 FROM oc_event WHERE code LIKE 'ocs_listing_%'")->fetchAll());
        $this->assertCount(1, $db->pdo->query("SELECT 1 FROM oc_modification WHERE code = 'ocs_listing_sort'")->fetchAll());
        $this->assertTrue($m->refreshRequired);
    }
}
