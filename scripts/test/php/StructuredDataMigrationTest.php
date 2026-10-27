<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\Migrate\Context;
use Ocs\Test\Support\SqliteDb;

final class StructuredDataMigrationTest extends TestCase
{
    // TEST-SD-007 - verifies REQ-SD-009 (settings with defaults, existing values kept, one event, idempotent)
    public function test_settings_and_event(): void
    {
        $db = new SqliteDb();
        $db->createCoreSubset();
        $m = new Context($db, 'oc_', dirname(__DIR__, 3));
        $fn = require dirname(__DIR__, 3) . '/custom/db/migrations/0011_structured_data_settings.php';
        $fn($m);

        $this->assertSame('1', (string)$m->setting->get('theme_ocs', 'theme_ocs_schema_status'));
        $this->assertSame('', (string)$m->setting->get('theme_ocs', 'theme_ocs_schema_org_name'));

        $m->setting->set('theme_ocs', 'theme_ocs_schema_status', 0);
        $m->setting->set('theme_ocs', 'theme_ocs_schema_org_name', 'My Shop');
        $fn($m);

        $this->assertSame('0', (string)$m->setting->get('theme_ocs', 'theme_ocs_schema_status'), 'edited value kept');
        $this->assertSame('My Shop', (string)$m->setting->get('theme_ocs', 'theme_ocs_schema_org_name'));

        $rows = $db->rows("SELECT * FROM oc_event WHERE code = 'ocs_structured_data'");
        $this->assertSame(1, count($rows), 'registered once');
        $this->assertSame('catalog/view/*/after', $rows[0]['trigger']);
        $this->assertSame('extension/ocs/events/structuredData', $rows[0]['action']);
    }
}
