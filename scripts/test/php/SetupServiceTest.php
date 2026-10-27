<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\Setup\Service;
use Ocs\Test\Support\SqliteDb;
use Ocs\Test\Support\TempRepo;

// SPEC-040: in-store setup service (precheck, install without switching the theme, activate, uninstall).
final class SetupServiceTest extends TestCase
{
    /** @var SqliteDb */
    private $db;
    /** @var TempRepo */
    private $repo;

    protected function setUp(): void
    {
        $this->db = new SqliteDb();
        $this->db->createCoreSubset();
        $this->db->pdo->exec('CREATE TABLE oc_category (category_id INTEGER PRIMARY KEY)');
        $this->db->pdo->exec('CREATE TABLE oc_product (product_id INTEGER PRIMARY KEY)');
        $this->db->pdo->exec('CREATE TABLE oc_layout (layout_id INTEGER PRIMARY KEY)');
        $this->db->pdo->exec("INSERT INTO oc_setting (store_id, code, `key`, `value`) VALUES (0, 'config', 'config_theme', 'default')");
        $this->repo = new TempRepo();
        $this->repo->put('VERSION', "9.9.9\n");
        // Mimics migration 0001: registers the theme and (as the dev stand does) selects it.
        $this->repo->migration('0001_theme.php', <<<'PHP'
<?php
declare(strict_types=1);

return function (\Ocs\Migrate\Context $m): void {
    $m->setting->set('theme_ocs', 'theme_ocs_status', 1);
    $m->event->register('ocs_theme_header_before', 'catalog/view/common/header/before', 'extension/ocs/events/beforeHeader', 0);
    $m->setting->set('config', 'config_theme', 'ocs');
};
PHP
        );
        $this->repo->extMigration('ocs_demo', '001_install.php', <<<'PHP'
<?php
declare(strict_types=1);

return function (\Ocs\Migrate\Context $m): void {
    $m->setting->set('module_ocs_demo', 'module_ocs_demo_status', 0);
};
PHP
        );
        $this->repo->put('custom/extensions/ocs_demo/extension.json', '{"code":"ocs_demo","type":"module","requires":{"extensions":[]}}');
    }

    protected function tearDown(): void
    {
        $this->repo->cleanup();
    }

    private function service(): Service
    {
        return new Service($this->db, 'oc_', $this->repo->root, static function (string $app, string $rel): bool {
            return $rel === 'catalog/controller/extension/ocs/events.php';
        });
    }

    private function setting(string $key): ?string
    {
        $v = $this->db->pdo->query("SELECT value FROM oc_setting WHERE `key` = '{$key}'")->fetchColumn();
        return $v === false ? null : (string) $v;
    }

    // TEST-PKG-004 - verifies REQ-PKG-007
    public function test_precheck_reports_old_core_and_missing_tables(): void
    {
        $this->db->pdo->exec('DROP TABLE oc_product');
        $problems = $this->service()->precheck(['core_version' => '2.3.0.2', 'php_version' => '7.4.0']);
        $this->assertTrue(count($problems) >= 2);
        $this->assertTrue(in_array('required table is missing: oc_product', $problems, true));
        $this->assertTrue(in_array('package payload is incomplete (custom/db/migrations not found)', (new Service($this->db, 'oc_', $this->repo->root . '/nope'))->precheck(), true));
    }

    // TEST-PKG-005 - verifies REQ-PKG-005, REQ-PKG-006
    public function test_install_runs_migrations_and_keeps_active_theme(): void
    {
        $r = $this->service()->install(['core_version' => '3.0.3.7', 'php_version' => '7.4.0']);
        $this->assertTrue($r['ok']);
        $this->assertSame('default', $this->setting('config_theme'));
        $this->assertSame('9.9.9', $this->setting('ocs_package_version'));
        $this->assertSame('1', $this->setting('theme_ocs_status'));
        $this->assertSame('0', $this->setting('module_ocs_demo_status'));
        $this->assertSame(1, (int) $this->db->pdo->query("SELECT COUNT(*) FROM oc_event WHERE code = 'ocs_theme_header_before'")->fetchColumn());
    }

    // TEST-PKG-006 - verifies REQ-PKG-005 (re-run is a no-op)
    public function test_install_twice_applies_nothing_the_second_time(): void
    {
        $this->service()->install();
        $second = $this->service()->install();
        $this->assertTrue($second['ok']);
        $this->assertTrue(in_array('nothing to do', $second['log'], true));
        $this->assertSame(1, (int) $this->db->pdo->query("SELECT COUNT(*) FROM oc_event")->fetchColumn());
    }

    // TEST-OCM-001 - verifies REQ-OCM-001, REQ-OCM-002, REQ-OCM-003, REQ-OCM-010
    public function test_ocmod_is_written_to_system_dir_and_restored_on_every_install(): void
    {
        $xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<modification><name>T</name><code>ocs_t</code><version>1.0</version><author>me</author><link></link></modification>";
        $this->repo->put('custom/ocmod/ocs_t.ocmod.xml', $xml);
        $this->repo->migration('0002_mod.php', "<?php\ndeclare(strict_types=1);\nreturn function (\\Ocs\\Migrate\\Context \$m): void {\n    \$m->ocmod->install('custom/ocmod/ocs_t.ocmod.xml');\n};\n");
        $sys = $this->repo->root . '/sysdir';
        mkdir($sys);
        $s = new Service($this->db, "oc_", $this->repo->root, static function (string $app, string $rel): bool {
            return true;
        }, $sys);
        $this->db->pdo->exec("INSERT INTO oc_modification (extension_install_id, name, code, author, version, link, xml, status, date_added) VALUES (0, 'old', 'ocs_old', 'me', '1', '', '<x/>', 1, '2024-01-01')");
        $r = $s->install();
        $this->assertTrue($r["ok"]);
        $this->assertSame($xml, file_get_contents($sys . '/ocs_t.ocmod.xml'));
        $this->assertSame(0, (int) $this->db->pdo->query('SELECT COUNT(*) FROM oc_modification')->fetchColumn());
        unlink($sys . '/ocs_t.ocmod.xml');
        $again = $s->install();
        $this->assertTrue($again['refresh']);
        $this->assertSame($xml, file_get_contents($sys . '/ocs_t.ocmod.xml'));
        $s->uninstall();
        $this->assertFalse(is_file($sys . '/ocs_t.ocmod.xml'));
    }

    // TEST-OCM-002 - verifies REQ-OCM-004
    public function test_precheck_reports_unwritable_system_dir(): void
    {
        $s = new Service($this->db, 'oc_', $this->repo->root, null, $this->repo->root . '/no-such-dir');
        $this->assertTrue(in_array('not writable: ' . $this->repo->root . '/no-such-dir', $s->precheck(), true));
    }

    // TEST-PKG-007 - verifies REQ-PKG-006
    public function test_activate_selects_theme_and_switches_modules_on(): void
    {
        $s = $this->service();
        $s->install();
        $s->activate();
        $this->assertSame('ocs', $this->setting('config_theme'));
        $this->assertSame('1', $this->setting('module_ocs_demo_status'));
    }

    // TEST-PKG-008 - verifies REQ-PKG-010
    public function test_uninstall_restores_default_theme_and_keeps_data(): void
    {
        $s = $this->service();
        $s->install();
        $s->activate();
        $r = $s->uninstall();
        $this->assertTrue($r['refresh']);
        $this->assertSame('default', $this->setting('config_theme'));
        $this->assertSame('0', $this->setting('module_ocs_demo_status'));
        $this->assertSame(0, (int) $this->db->pdo->query('SELECT COUNT(*) FROM oc_event')->fetchColumn());
        $this->assertSame(0, (int) $this->db->pdo->query('SELECT COUNT(*) FROM oc_ocs_migration')->fetchColumn());
        $again = $this->service()->install();
        $this->assertTrue($again['ok']);
        $this->assertSame(1, (int) $this->db->pdo->query('SELECT COUNT(*) FROM oc_event')->fetchColumn());
    }

    // TEST-PKG-003 - verifies REQ-PKG-021 (event routes are resolved against the installed store, not the repo)
    public function test_event_route_is_checked_with_the_store_resolver(): void
    {
        $svc = new Service($this->db, 'oc_', $this->repo->root, static function (string $app, string $rel): bool {
            return false;
        });
        $r = $svc->install();
        $this->assertTrue(!$r['ok']);
    }
}
