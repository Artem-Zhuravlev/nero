<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\Migrate\Context;
use Ocs\Migrate\MigrationException;
use Ocs\Test\Support\SqliteDb;
use Ocs\Test\Support\TempRepo;

final class HelpersTest extends TestCase
{
    /** @var SqliteDb */
    private $db;
    /** @var TempRepo */
    private $repo;
    /** @var Context */
    private $m;
    /** @var string[] */
    private $logs = [];

    protected function setUp(): void
    {
        $this->db = new SqliteDb();
        $this->db->createCoreSubset();
        $this->repo = new TempRepo();
        $this->logs = [];
        $this->m = new Context($this->db, 'oc_', $this->repo->root, false, function (string $l): void {
            $this->logs[] = $l;
        });
    }

    protected function tearDown(): void
    {
        $this->repo->cleanup();
    }

    // TEST-DB-006 - verifies REQ-DB-020 (idempotent per-key upsert, duplicates collapsed, arrays serialized)
    public function test_setting_set_is_idempotent_and_collapses_duplicates(): void
    {
        $this->assertSame('created', $this->m->setting->set('config', 'config_name', 'Shop'));
        $this->assertSame('unchanged', $this->m->setting->set('config', 'config_name', 'Shop'));
        $this->assertSame('updated', $this->m->setting->set('config', 'config_name', 'Shop 2'));
        $this->db->pdo->exec("INSERT INTO oc_setting (store_id, code, `key`, `value`, serialized) VALUES (0, 'config', 'config_name', 'dup', 0)");
        $this->assertSame('updated', $this->m->setting->set('config', 'config_name', 'Shop 2'));
        $rows = $this->db->pdo->query("SELECT * FROM oc_setting WHERE `key` = 'config_name'")->fetchAll();
        $this->assertCount(1, $rows);
        $this->assertSame('Shop 2', $rows[0]['value']);
        $this->m->setting->set('config', 'config_status_list', ['5', '3']);
        $this->assertSame(['5', '3'], $this->m->setting->get('config', 'config_status_list'));
        $this->assertSame('1', (string) $this->db->pdo->query("SELECT serialized FROM oc_setting WHERE `key`='config_status_list'")->fetchColumn());
    }

    // verifies REQ-DB-020: other keys of the same code are NOT wiped (unlike core editSetting)
    public function test_setting_set_keeps_other_keys_of_same_code(): void
    {
        $this->m->setting->setMany('module_x', ['module_x_status' => 1, 'module_x_title' => "It's"]);
        $this->m->setting->set('module_x', 'module_x_status', 0);
        $this->assertSame("It's", $this->m->setting->get('module_x', 'module_x_title'));
        $this->assertSame('0', $this->m->setting->get('module_x', 'module_x_status'));
        $this->assertSame(1, $this->m->setting->delete('module_x', 'module_x_status'));
    }

    // TEST-DB-006 - verifies REQ-DB-021
    public function test_extension_register_twice_creates_one_row_and_validates(): void
    {
        $this->assertSame('created', $this->m->extension->register('module', 'ocs_hello'));
        $this->assertSame('unchanged', $this->m->extension->register('module', 'ocs_hello'));
        $this->assertSame('1', (string) $this->db->pdo->query('SELECT COUNT(*) FROM oc_extension')->fetchColumn());
        $this->assertThrows(MigrationException::class, function (): void {
            $this->m->extension->register('bogus', 'ocs_x');
        }, '/unknown extension type/');
        $this->assertThrows(MigrationException::class, function (): void {
            $this->m->extension->register('module', 'ocs_' . str_repeat('a', 40));
        }, '/invalid extension code/');
        $this->assertTrue($this->m->refreshRequired);
    }

    // TEST-DB-006 - verifies REQ-DB-022 (grant twice, preserves foreign keys such as "hiden", by name or id)
    public function test_permission_grant_is_idempotent_and_preserves_other_keys(): void
    {
        $this->db->pdo->exec("UPDATE oc_user_group SET permission = '{\"access\":[\"a\"],\"modify\":[\"a\"],\"hiden\":[\"x\"]}' WHERE user_group_id = 1");
        $this->assertSame('updated', $this->m->permission->grant('Administrator', 'extension/module/ocs_hello'));
        $this->assertSame('unchanged', $this->m->permission->grant(1, 'extension/module/ocs_hello'));
        $data = json_decode((string) $this->db->pdo->query('SELECT permission FROM oc_user_group WHERE user_group_id = 1')->fetchColumn(), true);
        $this->assertSame(['a', 'extension/module/ocs_hello'], $data['access']);
        $this->assertSame(['a', 'extension/module/ocs_hello'], $data['modify']);
        $this->assertSame(['x'], $data['hiden']);
        $this->assertSame('updated', $this->m->permission->revoke(1, 'extension/module/ocs_hello'));
        $this->assertFalse($this->m->permission->has(1, 'extension/module/ocs_hello'));
    }

    public function test_permission_grant_handles_empty_and_invalid_json(): void
    {
        $this->assertSame('updated', $this->m->permission->grant(10, 'r/one'));
        $this->db->pdo->exec("UPDATE oc_user_group SET permission = 'not json' WHERE user_group_id = 10");
        $this->assertSame('updated', $this->m->permission->grant(10, 'r/two'));
        $this->assertLineMatches('/invalid permission JSON/', $this->logs);
        $this->assertThrows(MigrationException::class, function (): void {
            $this->m->permission->grant(999, 'x');
        }, '/not found/');
    }

    // TEST-DB-007 - verifies REQ-DB-023
    public function test_event_register_validates_trigger_code_and_action(): void
    {
        $this->repo->put('custom/extensions/ocs_x/upload/catalog/controller/extension/ocs_x/events.php', '<?php');
        $t = 'catalog/model/checkout/order/addOrderHistory/after';
        $this->assertSame('created', $this->m->event->register('ocs_x_after_history', $t, 'extension/ocs_x/events/afterHistory', 150));
        $this->assertSame('unchanged', $this->m->event->register('ocs_x_after_history', $t, 'extension/ocs_x/events/afterHistory', 150));
        $this->assertSame('updated', $this->m->event->register('ocs_x_after_history', $t, 'extension/ocs_x/events/afterHistory', 160));
        $this->assertSame('1', (string) $this->db->pdo->query('SELECT COUNT(*) FROM oc_event')->fetchColumn());
        $this->assertThrows(MigrationException::class, function (): void {
            $this->m->event->register('core_event', 'catalog/model/a/b/after', 'extension/ocs_x/events/x');
        }, '/must match ocs_/');
        $this->assertThrows(MigrationException::class, function (): void {
            $this->m->event->register('ocs_bad', 'storefront/model/a/b/after', 'extension/ocs_x/events/x');
        }, '/invalid event trigger/');
        $this->assertThrows(MigrationException::class, function (): void {
            $this->m->event->register('ocs_bad', 'catalog/model/a/*/after', 'extension/ocs_x/events/x');
        }, '/wildcards/');
        $this->assertThrows(MigrationException::class, function (): void {
            $this->m->event->register('ocs_bad', 'catalog/view/common/header/before', 'extension/ocs_missing/events/x');
        }, '/no catalog controller file/');
        $this->assertSame('created', $this->m->event->register('ocs_wild', 'catalog/view/*/before', 'extension/ocs_x/events/x'));
        $this->assertSame(1, $this->m->event->unregisterByCode('ocs_wild'));
    }

    // TEST-DB-007 - verifies REQ-DB-024
    public function test_seo_url_set_detects_keyword_conflicts(): void
    {
        $this->assertSame('created', $this->m->seoUrl->set('information_id=4', 'about', 'uk-ua'));
        $this->assertSame('unchanged', $this->m->seoUrl->set('information_id=4', 'about', 'uk-ua'));
        $this->assertSame('updated', $this->m->seoUrl->set('information_id=4', 'about-us', 'uk-ua'));
        $this->assertSame('created', $this->m->seoUrl->set('information_id=4', 'about', 'en-gb'));
        $this->assertThrows(MigrationException::class, function (): void {
            $this->m->seoUrl->set('information_id=5', 'about-us', 'uk-ua');
        }, '/already used by/');
        $this->assertThrows(MigrationException::class, function (): void {
            $this->m->seoUrl->set('x', 'Bad Keyword', 'uk-ua');
        }, '/invalid SEO keyword/');
        $this->assertThrows(MigrationException::class, function (): void {
            $this->m->seoUrl->set('x', 'ok', 'xx-xx');
        }, '/language .xx-xx. not found/');
    }

    // TEST-DB-007 - verifies REQ-DB-025, REQ-DB-026
    public function test_layout_and_module_helpers_deduplicate(): void
    {
        $this->db->pdo->exec("INSERT INTO oc_layout_route (layout_id, store_id, route) VALUES (1, 0, 'common/home')");
        $this->assertSame(1, $this->m->layout->idByRoute('common/home'));
        $this->assertThrows(MigrationException::class, function (): void {
            $this->m->layout->idByRoute('nope');
        }, '/no layout/');
        $this->assertSame('created', $this->m->layout->routeAdd(1, 'ocs/page'));
        $this->assertSame('unchanged', $this->m->layout->routeAdd(1, 'ocs/page'));
        $id = $this->m->module->upsert('ocs_hello', 'home_hello', ['status' => 1]);
        $this->assertSame($id, $this->m->module->upsert('ocs_hello', 'home_hello', ['status' => 1]));
        $this->assertSame('created', $this->m->layout->moduleAdd(1, "ocs_hello.{$id}", 'content_top', 3));
        $this->assertSame('unchanged', $this->m->layout->moduleAdd(1, "ocs_hello.{$id}", 'content_top', 3));
        $this->assertSame('updated', $this->m->layout->moduleAdd(1, "ocs_hello.{$id}", 'column_left', 3));
        $this->assertThrows(MigrationException::class, function (): void {
            $this->m->layout->moduleAdd(1, 'x', 'sidebar', 0);
        }, '/invalid layout position/');
    }

    // TEST-DB-008 - verifies REQ-DB-027
    public function test_ocmod_install_is_keyed_by_code_and_validates(): void
    {
        $xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<modification><name>T</name><code>ocs_t</code><version>1.0</version><author>me</author><link>http://x</link></modification>";
        $path = $this->repo->put('custom/ocmod/ocs_t.ocmod.xml', $xml);
        $this->assertSame('created', $this->m->ocmod->install($path));
        $this->assertSame('unchanged', $this->m->ocmod->install($path));
        $this->repo->put('custom/ocmod/ocs_t.ocmod.xml', str_replace('1.0', '1.1', $xml));
        $this->assertSame('updated', $this->m->ocmod->install($path));
        $this->assertSame('1', (string) $this->db->pdo->query('SELECT COUNT(*) FROM oc_modification')->fetchColumn());
        $bad = $this->repo->put('custom/ocmod/ocs_other.ocmod.xml', str_replace('ocs_t', 'ocs_x', $xml));
        $this->assertThrows(MigrationException::class, function () use ($bad): void {
            $this->m->ocmod->install($bad);
        }, '/equal the file stem/');
        $this->assertThrows(MigrationException::class, function (): void {
            $this->m->ocmod->install('/no/such/file.xml');
        }, '/not found/');
        $this->assertSame(1, $this->m->ocmod->remove('ocs_t'));
    }

    // TEST-DB-008 - verifies REQ-DB-028
    public function test_lookups_fail_loudly(): void
    {
        $this->assertSame(1, $this->m->lang->id('uk-ua'));
        $this->assertSame(1, $this->m->currency->id('UAH'));
        $this->assertSame(1, $this->m->group->id('Administrator'));
        $this->assertSame(1, $this->m->orderStatus->id('Pending'));
        $this->assertThrows(MigrationException::class, function (): void {
            $this->m->currency->id('XXX');
        }, '/not found/');
    }

    // TEST-DB-008 - verifies REQ-DB-029, REQ-DB-030
    public function test_sql_binding_and_schema_helpers_only_touch_own_tables(): void
    {
        $this->assertSame("INSERT INTO oc_a VALUES ('it''s', 5, NULL)", $this->m->bind('INSERT INTO {{PREFIX}}a VALUES (?, ?, ?)', ["it's", 5, null]));
        $this->assertThrows(MigrationException::class, function (): void {
            $this->m->bind('SELECT ?', []);
        }, '/placeholder count/');
        $this->m->createTable('ocs_demo_item', 'CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_demo_item (item_id INTEGER PRIMARY KEY, name TEXT)');
        $this->m->createTable('ocs_demo_item', 'CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_demo_item (item_id INTEGER PRIMARY KEY, name TEXT)');
        $this->m->addColumnIfMissing('ocs_demo_item', 'status', 'INTEGER NOT NULL DEFAULT 1');
        $this->m->addColumnIfMissing('ocs_demo_item', 'status', 'INTEGER NOT NULL DEFAULT 1');
        $this->m->addIndexIfMissing('ocs_demo_item', 'idx_name', ['name']);
        $this->m->addIndexIfMissing('ocs_demo_item', 'idx_name', ['name']);
        $this->assertTrue($this->db->columnExists('oc_ocs_demo_item', 'status'));
        $this->assertTrue($this->db->indexExists('oc_ocs_demo_item', 'idx_name'));
        $this->assertThrows(MigrationException::class, function (): void {
            $this->m->createTable('product', 'CREATE TABLE IF NOT EXISTS {{PREFIX}}product (x INT)');
        }, '/non-custom table/');
        $this->assertThrows(MigrationException::class, function (): void {
            $this->m->addColumnIfMissing('setting', 'x', 'INT');
        }, '/non-custom table/');
        $this->assertThrows(MigrationException::class, function (): void {
            $this->m->createTable('ocs_demo2', 'CREATE TABLE {{PREFIX}}ocs_demo2 (x INT)');
        }, '/IF NOT EXISTS/');
        $this->assertThrows(MigrationException::class, function (): void {
            $this->m->dropTableIfExists('ocs_demo_item');
        }, '/only in \*_uninstall/');
        $un = new Context($this->db, 'oc_', $this->repo->root, true);
        $un->dropTableIfExists('ocs_demo_item');
        $this->assertFalse($this->db->tableExists('oc_ocs_demo_item'));
    }
}
