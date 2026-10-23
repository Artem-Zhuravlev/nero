<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\Migrate\Context;
use Ocs\Test\Support\SqliteDb;

final class ThemeMigrationTest extends TestCase
{
    /** @var SqliteDb */
    private $db;
    /** @var Context */
    private $m;

    protected function setUp(): void
    {
        $this->db = new SqliteDb();
        $this->db->createCoreSubset();
        $this->m = new Context($this->db, 'oc_', dirname(__DIR__, 3));
    }

    private function run0001(): void
    {
        $fn = require dirname(__DIR__, 3) . '/custom/db/migrations/0001_theme_ocs.php';
        $fn($this->m);
    }

    private function status(): string
    {
        return (string) $this->db->pdo->query("SELECT value FROM oc_setting WHERE `key` = 'theme_ocs_status'")->fetchColumn();
    }

    // TEST-THM-002 - verifies REQ-THM-002, REQ-THM-003 (registers, copies theme_default_*, status before config_theme)
    public function test_registers_theme_copies_defaults_and_activates(): void
    {
        $this->m->setting->set('theme_default', 'theme_default_image_thumb_width', 300);
        $this->m->setting->set('theme_default', 'theme_default_product_limit', 24);
        $this->run0001();

        $this->assertTrue($this->m->extension->isRegistered('theme', 'ocs'));
        $this->assertTrue($this->m->permission->has(1, 'extension/theme/ocs', 'modify'));
        $this->assertSame('300', (string) $this->m->setting->get('theme_ocs', 'theme_ocs_image_thumb_width'));
        $this->assertSame('24', (string) $this->m->setting->get('theme_ocs', 'theme_ocs_product_limit'));
        $this->assertSame('228', (string) $this->m->setting->get('theme_ocs', 'theme_ocs_image_product_width'));
        $this->assertSame('ocs', $this->m->setting->get('theme_ocs', 'theme_ocs_directory'));
        $this->assertSame('1', $this->status());
        $this->assertSame('ocs', $this->m->setting->get('config', 'config_theme'));

        $order = array_values(array_filter($this->db->log, static function (string $q): bool {
            return strpos($q, 'theme_ocs_status') !== false || strpos($q, 'config_theme') !== false;
        }));
        $statusAt = -1;
        $themeAt = -1;
        foreach ($order as $i => $q) {
            if ($statusAt < 0 && strpos($q, "'theme_ocs_status'") !== false && stripos($q, 'INSERT') === 0) {
                $statusAt = $i;
            }
            if ($themeAt < 0 && strpos($q, "'config_theme'") !== false && stripos($q, 'INSERT') === 0) {
                $themeAt = $i;
            }
        }
        $this->assertTrue($statusAt >= 0 && $statusAt < $themeAt, 'theme_ocs_status must be written before config_theme');
    }

    // TEST-THM-006 - verifies REQ-THM-022 (header/before event registered, idempotent migration)
    public function test_registers_asset_version_event_and_is_idempotent(): void
    {
        $this->run0001();
        $this->run0001();
        $rows = $this->db->pdo->query("SELECT action FROM oc_event WHERE code = 'ocs_theme_header_before' AND `trigger` = 'catalog/view/common/header/before'")->fetchAll();
        $this->assertCount(1, $rows);
        $this->assertSame('extension/ocs/events/beforeHeader', $rows[0]['action']);
        $this->assertCount(1, $this->db->pdo->query("SELECT 1 FROM oc_extension WHERE type='theme' AND code='ocs'")->fetchAll());
    }

    // TEST-DSG-012 - verifies REQ-THM-030 (design image sizes set by migration 0002, other roles untouched, idempotent)
    public function test_image_sizes_migration_sets_design_values(): void
    {
        $this->run0001();
        $this->m->setting->set('theme_ocs', 'theme_ocs_image_cart_width', 55);
        $fn = require dirname(__DIR__, 3) . '/custom/db/migrations/0002_theme_ocs_image_sizes.php';
        $fn($this->m);
        $fn($this->m);
        $this->assertSame('600', (string) $this->m->setting->get('theme_ocs', 'theme_ocs_image_thumb_width'));
        $this->assertSame('1200', (string) $this->m->setting->get('theme_ocs', 'theme_ocs_image_popup_height'));
        $this->assertSame('400', (string) $this->m->setting->get('theme_ocs', 'theme_ocs_image_product_width'));
        $this->assertSame('100', (string) $this->m->setting->get('theme_ocs', 'theme_ocs_image_additional_height'));
        $this->assertSame('800', (string) $this->m->setting->get('theme_ocs', 'theme_ocs_image_category_width'));
        $this->assertSame('55', (string) $this->m->setting->get('theme_ocs', 'theme_ocs_image_cart_width'));
    }

    // verifies REQ-THM-002: existing theme_ocs_* values are never overwritten by a re-run
    public function test_rerun_keeps_edited_theme_values(): void
    {
        $this->run0001();
        $this->m->setting->set('theme_ocs', 'theme_ocs_image_thumb_width', 640);
        $this->m->setting->set('theme_default', 'theme_default_image_thumb_width', 111);
        $this->run0001();
        $this->assertSame('640', (string) $this->m->setting->get('theme_ocs', 'theme_ocs_image_thumb_width'));
    }
}
