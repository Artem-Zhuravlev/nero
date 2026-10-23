<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\Migrate\Context;
use Ocs\Test\Support\SqliteDb;

final class NoindexMigrationTest extends TestCase
{
    // TEST-THM-002 - verifies REQ-THM-050 (listings must not log core's undefined $disallow_params): setting filled, existing value kept
    public function test_sets_disallow_params_once(): void
    {
        $db = new SqliteDb();
        $db->createCoreSubset();
        $m = new Context($db, 'oc_', dirname(__DIR__, 3));
        $fn = require dirname(__DIR__, 3) . '/custom/db/migrations/0003_noindex_disallow_params.php';
        $fn($m);
        $this->assertSame('page', $m->setting->get('config', 'config_noindex_disallow_params'));
        $m->setting->set('config', 'config_noindex_disallow_params', "page\r\nsort");
        $fn($m);
        $this->assertSame("page\r\nsort", $m->setting->get('config', 'config_noindex_disallow_params'));
    }
}
