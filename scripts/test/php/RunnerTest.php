<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\Migrate\Discovery;
use Ocs\Migrate\MigrationException;
use Ocs\Migrate\Runner;
use Ocs\Migrate\SqlFile;
use Ocs\Test\Support\SqliteDb;
use Ocs\Test\Support\TempRepo;

final class RunnerTest extends TestCase
{
    /** @var SqliteDb */
    private $db;
    /** @var TempRepo */
    private $repo;
    /** @var string[] */
    private $out = [];

    protected function setUp(): void
    {
        $this->db = new SqliteDb();
        $this->db->createCoreSubset();
        $this->repo = new TempRepo();
        $this->out = [];
    }

    protected function tearDown(): void
    {
        $this->repo->cleanup();
    }

    private function runner(): Runner
    {
        return new Runner($this->db, 'oc_', $this->repo->root, function (string $l): void {
            $this->out[] = $l;
        });
    }

    private const PHP_OK = "<?php\ndeclare(strict_types=1);\n\nreturn function (\\Ocs\\Migrate\\Context \$m): void {\n    \$m->setting->set('config', '%s', '%s');\n};\n";

    private function phpMigration(string $file, string $key, string $value = '1'): void
    {
        $this->repo->migration($file, sprintf(self::PHP_OK, $key, $value));
    }

    // TEST-DB-002 - verifies REQ-DB-010 (numbering, no gaps/duplicates)
    public function test_discovery_rejects_gaps_and_bad_names(): void
    {
        $this->phpMigration('0001_a.php', 'k1');
        $this->phpMigration('0003_c.php', 'k3');
        $this->assertThrows(MigrationException::class, function (): void {
            Discovery::core($this->repo->root);
        }, '/expected 0002, found 0003/');
        $this->repo->put('custom/db/migrations/0003_c.php', '<?php');
        unlink($this->repo->root . '/custom/db/migrations/0003_c.php');
        $this->repo->migration('0002_Bad-Name.sql', 'SELECT 1;');
        $this->assertThrows(MigrationException::class, function (): void {
            Discovery::core($this->repo->root);
        }, '/bad core migration file name/');
    }

    // TEST-DB-002 - verifies REQ-DB-004 (core first, extensions in dependency order, cycles/missing detected)
    public function test_extension_dependency_order(): void
    {
        $this->repo->put('custom/extensions/ocs_b/extension.json', json_encode(['code' => 'ocs_b', 'requires' => ['extensions' => ['ocs_a']]]));
        $this->repo->put('custom/extensions/ocs_a/extension.json', json_encode(['code' => 'ocs_a']));
        $this->repo->put('custom/extensions/ocs_c/extension.json', json_encode(['code' => 'ocs_c']));
        $this->assertSame(['ocs_a', 'ocs_b', 'ocs_c'], Discovery::extensionOrder($this->repo->root));
        $this->repo->put('custom/extensions/ocs_a/extension.json', json_encode(['code' => 'ocs_a', 'requires' => ['extensions' => ['ocs_b']]]));
        $this->assertThrows(MigrationException::class, function (): void {
            Discovery::extensionOrder($this->repo->root);
        }, '/cycle/');
        $this->repo->put('custom/extensions/ocs_a/extension.json', json_encode(['code' => 'ocs_a', 'requires' => ['extensions' => ['ocs_zzz']]]));
        $this->assertThrows(MigrationException::class, function (): void {
            Discovery::extensionOrder($this->repo->root);
        }, '/requires missing extension/');
    }

    // TEST-DB-001 - verifies REQ-DB-001, REQ-DB-003 (bookkeeping), REQ-DB-004
    public function test_up_applies_in_order_and_second_run_is_noop(): void
    {
        $this->phpMigration('0001_a.php', 'k1');
        $this->repo->migration('0002_tbl.sql', "-- comment\nCREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_t (id INTEGER);\nINSERT INTO {{PREFIX}}ocs_t (id) VALUES (1);\n");
        $this->repo->put('custom/extensions/ocs_x/extension.json', json_encode(['code' => 'ocs_x']));
        $this->repo->extMigration('ocs_x', '001_install.php', sprintf(self::PHP_OK, 'ext_key', 'v'));
        $this->assertSame(Runner::EXIT_OK, $this->runner()->up());
        $this->assertSame('1', (string) $this->db->pdo->query("SELECT value FROM oc_setting WHERE `key`='k1'")->fetchColumn());
        $this->assertSame('v', (string) $this->db->pdo->query("SELECT value FROM oc_setting WHERE `key`='ext_key'")->fetchColumn());
        $ids = $this->db->pdo->query('SELECT id FROM oc_ocs_migration ORDER BY applied_at, rowid')->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame(['core:0001_a', 'core:0002_tbl', 'ext:ocs_x:001_install'], $ids);
        $this->out = [];
        $this->assertSame(Runner::EXIT_OK, $this->runner()->up());
        $this->assertLineMatches('/nothing to do/', $this->out);
        $this->assertSame('1', (string) $this->db->pdo->query('SELECT COUNT(*) FROM oc_ocs_t')->fetchColumn());
        foreach ($this->runner()->status() as $row) {
            $this->assertSame('applied', $row['state']);
        }
    }

    // TEST-DB-003 - verifies REQ-DB-006 (checksum mismatch aborts, nothing else runs)
    public function test_editing_an_applied_migration_aborts_with_exit_3(): void
    {
        $this->phpMigration('0001_a.php', 'k1');
        $this->assertSame(Runner::EXIT_OK, $this->runner()->up());
        $this->phpMigration('0001_a.php', 'k1', 'EDITED');
        $this->phpMigration('0002_b.php', 'k2');
        $this->assertSame(Runner::EXIT_CHECKSUM, $this->runner()->up());
        $this->assertLineMatches('/checksum mismatch for applied migration core:0001_a/', $this->out);
        $this->assertSame('0', (string) $this->db->pdo->query("SELECT COUNT(*) FROM oc_setting WHERE `key`='k2'")->fetchColumn());
        $this->assertSame('changed', $this->runner()->status()[0]['state']);
        // --accept-checksum (whitespace-only fixes, local env) re-applies that migration
        $this->assertSame(Runner::EXIT_OK, $this->runner()->up(['acceptChecksum' => ['core:0001_a']]));
    }

    // checksum is CRLF-insensitive (REQ-DB-003)
    public function test_checksum_ignores_line_ending_style(): void
    {
        $this->repo->migration('0001_a.sql', "SELECT 1;\nSELECT 2;\n");
        $m1 = Discovery::core($this->repo->root)[0]->checksum();
        $this->repo->migration('0001_a.sql', "SELECT 1;\r\nSELECT 2;\r\n");
        $this->assertSame($m1, Discovery::core($this->repo->root)[0]->checksum());
    }

    // TEST-DB-003 - verifies REQ-DB-005 (failure recorded, run stops, re-run completes without duplicates)
    public function test_failed_migration_is_recorded_and_rerun_after_fix(): void
    {
        $flag = $this->repo->root . '/fail.flag';
        file_put_contents($flag, '1');
        $this->repo->migration('0001_flaky.php', "<?php\ndeclare(strict_types=1);\n\nreturn function (\\Ocs\\Migrate\\Context \$m): void {\n    \$m->setting->set('config', 'step_one', 'done');\n    if (is_file('" . $flag . "')) {\n        throw new \\RuntimeException('boom');\n    }\n    \$m->setting->set('config', 'step_two', 'done');\n};\n");
        $this->phpMigration('0002_after.php', 'after');
        $this->assertSame(Runner::EXIT_FAILED, $this->runner()->up());
        $this->assertLineMatches('/FAIL core:0001_flaky: boom/', $this->out);
        $row = $this->db->pdo->query("SELECT status, note FROM oc_ocs_migration WHERE id='core:0001_flaky'")->fetch();
        $this->assertSame('failed', $row['status']);
        $this->assertSame('boom', $row['note']);
        $this->assertSame('0', (string) $this->db->pdo->query("SELECT COUNT(*) FROM oc_setting WHERE `key`='after'")->fetchColumn());
        $this->assertSame('failed', $this->runner()->status()[0]['state']);
        unlink($flag);
        $this->assertSame(Runner::EXIT_OK, $this->runner()->up());
        $this->assertSame('1', (string) $this->db->pdo->query("SELECT COUNT(*) FROM oc_setting WHERE `key`='step_one'")->fetchColumn(), 're-run must not duplicate step one');
        $this->assertSame('applied', $this->db->pdo->query("SELECT status FROM oc_ocs_migration WHERE id='core:0001_flaky'")->fetchColumn());
    }

    // TEST-DB-003 - verifies REQ-DB-007 (dry-run executes nothing and does not create the bookkeeping table)
    public function test_dry_run_changes_nothing(): void
    {
        $this->phpMigration('0001_a.php', 'k1');
        $this->repo->migration('0002_b.sql', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_dry (x INTEGER);\n");
        $this->assertSame(Runner::EXIT_OK, $this->runner()->up(['dryRun' => true]));
        $this->assertLineMatches('/would apply core:0001_a/', $this->out);
        $this->assertLineMatches('/CREATE TABLE IF NOT EXISTS \{\{PREFIX\}\}ocs_dry/', $this->out);
        $this->assertFalse($this->db->tableExists('oc_ocs_migration'));
        $this->assertFalse($this->db->tableExists('oc_ocs_dry'));
    }

    // TEST-DB-001 - verifies REQ-DB-008 (second runner is refused with exit 4)
    public function test_lock_prevents_concurrent_runs(): void
    {
        $this->phpMigration('0001_a.php', 'k1');
        $this->assertTrue($this->db->lock('ocs_migrate'));
        $this->assertSame(Runner::EXIT_LOCKED, $this->runner()->up());
        $this->db->unlock('ocs_migrate');
        $this->assertSame(Runner::EXIT_OK, $this->runner()->up());
    }

    // TEST-DB-003 - verifies REQ-DB-009
    public function test_non_local_requires_recent_backup(): void
    {
        $this->phpMigration('0001_a.php', 'k1');
        $this->assertSame(Runner::EXIT_FAILED, $this->runner()->up(['requireBackup' => true]));
        $this->assertLineMatches('/no backup newer than 24h/', $this->out);
        $this->repo->put('var/backups/20261015T000000Z/manifest.json', '{}');
        $this->assertSame(Runner::EXIT_OK, $this->runner()->up(['requireBackup' => true]));
    }

    // verifies REQ-DB-001 (--to)
    public function test_to_stops_after_given_migration(): void
    {
        $this->phpMigration('0001_a.php', 'k1');
        $this->phpMigration('0002_b.php', 'k2');
        $this->phpMigration('0003_c.php', 'k3');
        $this->assertSame(Runner::EXIT_OK, $this->runner()->up(['to' => 'core:0002_b']));
        $this->assertSame(['applied', 'applied', 'pending'], array_column($this->runner()->status(), 'state'));
    }

    // TEST-DB-004 - verifies REQ-DB-011, REQ-DB-012 (tokens, statement splitting, lint)
    public function test_sql_statements_tokens_and_lint(): void
    {
        $sql = "-- header\n# also comment\n\nCREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_a (\n  id INT\n);\nINSERT INTO {{PREFIX}}ocs_a (id) VALUES (1);\n";
        $this->assertCount(2, SqlFile::statements($sql));
        $this->assertSame([], SqlFile::lint($sql, false));
        $bad = SqlFile::lint("DROP TABLE {{PREFIX}}ocs_a;\nDELETE FROM {{PREFIX}}ocs_a;\nALTER TABLE {{PREFIX}}product ADD x INT;\nINSERT INTO oc_setting (a) VALUES (1);\nINSERT INTO {{PREFIX}}setting (a) VALUES (1);\n", false);
        $this->assertTrue(count($bad) >= 5, 'expected at least 5 problems, got ' . json_encode($bad));
        $this->assertSame([], array_filter(SqlFile::lint("DROP TABLE IF EXISTS {{PREFIX}}ocs_a;\nDELETE FROM {{PREFIX}}ocs_a;\n", true), static function (string $p): bool {
            return strpos($p, 'DROP') !== false || strpos($p, 'DELETE') !== false;
        }));
        $this->assertSame(['file must use LF line endings'], SqlFile::lint("SELECT 1;\r\n", false));
        $php = SqlFile::lintPhp("<?php\necho 'x';\nexit;\n");
        $this->assertTrue(count($php) >= 3);
    }

    // TEST-DB-004 - verifies REQ-DB-011 tokens {{LANG}} / {{GROUP}} / unknown token
    public function test_sql_token_expansion(): void
    {
        $ctx = new \Ocs\Migrate\Context($this->db, 'oc_', $this->repo->root);
        $this->assertSame('oc_x 2 1 0', SqlFile::expand('{{PREFIX}}x {{LANG:en-gb}} {{GROUP:Administrator}} {{STORE_ID}}', $ctx));
        $this->assertThrows(MigrationException::class, function () use ($ctx): void {
            SqlFile::expand('{{NOPE}}', $ctx);
        }, '/unknown SQL token/');
    }

    // TEST-DB-003 - preflight lint blocks the whole run (nothing executed)
    public function test_lint_failure_blocks_the_run(): void
    {
        $this->phpMigration('0001_ok.php', 'k1');
        $this->repo->migration('0002_bad.sql', "DROP TABLE {{PREFIX}}ocs_x;\n");
        $this->assertSame(Runner::EXIT_CHECKSUM, $this->runner()->up());
        $this->assertLineMatches('/FAIL lint core:0002_bad/', $this->out);
        $this->assertFalse($this->db->tableExists('oc_ocs_migration'));
    }

    // TEST-EXT-006 - verifies REQ-EXT-050 (down runs *_uninstall and forgets the extension's rows)
    public function test_down_runs_uninstall_and_forgets_extension_rows(): void
    {
        $this->repo->put('custom/extensions/ocs_x/extension.json', json_encode(['code' => 'ocs_x']));
        $this->repo->extMigration('ocs_x', '001_install.sql', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_x_item (id INTEGER);\n");
        $this->repo->extMigration('ocs_x', '002_uninstall.php', "<?php\ndeclare(strict_types=1);\n\nreturn function (\\Ocs\\Migrate\\Context \$m): void {\n    \$m->dropTableIfExists('ocs_x_item');\n};\n");
        $this->assertSame(Runner::EXIT_OK, $this->runner()->up());
        $this->assertTrue($this->db->tableExists('oc_ocs_x_item'));
        $this->assertSame(1, count($this->db->pdo->query("SELECT id FROM oc_ocs_migration WHERE id LIKE 'ext:ocs_x:%'")->fetchAll()), 'uninstall is not run by up');
        $this->assertSame(Runner::EXIT_OK, $this->runner()->down('ocs_x'));
        $this->assertFalse($this->db->tableExists('oc_ocs_x_item'));
        $this->assertSame(0, count($this->db->pdo->query("SELECT id FROM oc_ocs_migration WHERE id LIKE 'ext:ocs_x:%'")->fetchAll()));
        $this->assertSame(Runner::EXIT_FAILED, $this->runner()->down('ocs_nothing'));
    }

    // TEST-DB-005 - verifies REQ-DB-013 (.php must return a callable and be side-effect free)
    public function test_php_migration_must_return_closure(): void
    {
        $this->repo->migration('0001_bad.php', "<?php\ndeclare(strict_types=1);\n\n\$x = 1;\n");
        $this->assertSame(Runner::EXIT_CHECKSUM, $this->runner()->up());
        $this->assertLineMatches('/must return a closure/', $this->out);
    }
}
