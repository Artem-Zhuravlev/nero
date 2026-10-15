<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\Install\Installer;
use Ocs\Migrate\MigrationException;
use Ocs\Migrate\SqlFile;
use Ocs\Test\Support\SqliteDb;
use Ocs\Test\Support\TempRepo;

final class InstallerTest extends TestCase
{
    /** @var SqliteDb */
    private $db;
    /** @var TempRepo */
    private $repo;
    /** @var string */
    private $sqlFile;

    protected function setUp(): void
    {
        $this->db = new SqliteDb();
        $this->repo = new TempRepo();
        // Reduced stand-in for core/upload/install/opencart.sql (SQLite-compatible, same statement shapes).
        $this->sqlFile = $this->repo->put('opencart.sql', implode("\n", [
            '-- dump',
            'DROP TABLE IF EXISTS `oc_user`;',
            'CREATE TABLE `oc_user` (user_id INTEGER PRIMARY KEY, user_group_id INTEGER, username TEXT, salt TEXT, password TEXT, firstname TEXT, lastname TEXT, email TEXT, status INTEGER, date_added TEXT);',
            'DROP TABLE IF EXISTS `oc_setting`;',
            'CREATE TABLE `oc_setting` (setting_id INTEGER PRIMARY KEY AUTOINCREMENT, store_id INTEGER, `code` TEXT, `key` TEXT, `value` TEXT, serialized INTEGER DEFAULT 0);',
            "INSERT INTO `oc_setting` (store_id, `code`, `key`, `value`) VALUES (0, 'config', 'config_invoice_prefix', 'INV-2013-00');",
            'DROP TABLE IF EXISTS `oc_product`;',
            'CREATE TABLE `oc_product` (product_id INTEGER PRIMARY KEY, viewed INTEGER);',
            'INSERT INTO `oc_product` (product_id, viewed) VALUES (1, 55);',
            'DROP TABLE IF EXISTS `oc_api`;',
            'CREATE TABLE `oc_api` (api_id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT, `key` TEXT, status INTEGER, date_added TEXT, date_modified TEXT);',
            '',
        ]));
    }

    protected function tearDown(): void
    {
        $this->repo->cleanup();
    }

    private function install(array $over = []): void
    {
        (new Installer($this->db, function (string $m): void {
        }))->install($over + [
            'prefix' => 'xx_',
            'sqlFile' => $this->sqlFile,
            'username' => 'boss',
            'email' => 'boss@example.com',
            'password' => 'S3cret!pass',
            'year' => '2026',
            'now' => '2026-10-15 10:00:00',
        ]);
    }

    // TEST-ARCH-029 - verifies REQ-ARCH-032 (prefix replace, admin user, settings, api user, invoice prefix)
    public function test_install_replays_core_installer_steps(): void
    {
        $this->install();
        $pdo = $this->db->pdo;
        $user = $pdo->query('SELECT * FROM xx_user WHERE user_id = 1')->fetch();
        $this->assertSame('boss', $user['username']);
        $this->assertSame('1', (string) $user['user_group_id']);
        $this->assertSame(9, strlen($user['salt']));
        $this->assertSame(sha1($user['salt'] . sha1($user['salt'] . sha1('S3cret!pass'))), $user['password']);
        $this->assertSame('boss@example.com', $pdo->query("SELECT value FROM xx_setting WHERE `key`='config_email'")->fetchColumn());
        $this->assertSame(1024, strlen((string) $pdo->query("SELECT value FROM xx_setting WHERE `key`='config_encryption'")->fetchColumn()));
        $this->assertSame('INV-2026-00', $pdo->query("SELECT value FROM xx_setting WHERE `key`='config_invoice_prefix'")->fetchColumn());
        $this->assertSame('0', (string) $pdo->query('SELECT viewed FROM xx_product WHERE product_id = 1')->fetchColumn());
        $api = $pdo->query('SELECT * FROM xx_api')->fetchAll();
        $this->assertCount(1, $api);
        $this->assertSame('Default', $api[0]['username']);
        $this->assertSame(256, strlen($api[0]['key']));
        $this->assertSame((string) $api[0]['api_id'], (string) $pdo->query("SELECT value FROM xx_setting WHERE `key`='config_api_id'")->fetchColumn());
        $this->assertFalse($this->db->tableExists('oc_user'), 'no table keeps the original prefix');
    }

    public function test_install_refuses_second_run_without_force(): void
    {
        $this->install();
        $this->assertThrows(MigrationException::class, function (): void {
            $this->install();
        }, '/already installed/');
        $this->install(['force' => true]);
        $this->assertCount(1, $this->db->pdo->query('SELECT * FROM xx_api')->fetchAll());
    }

    public function test_install_validates_inputs(): void
    {
        $this->assertThrows(MigrationException::class, function (): void {
            $this->install(['prefix' => 'bad prefix!']);
        }, '/invalid table prefix/');
        $this->assertThrows(MigrationException::class, function (): void {
            $this->install(['sqlFile' => '/no/such.sql']);
        }, '/not found/');
    }

    public function test_token_and_hash_helpers(): void
    {
        $t = Installer::token(64);
        $this->assertSame(64, strlen($t));
        $this->assertTrue((bool) preg_match('/^[A-Za-z0-9]+$/', $t));
        $this->assertTrue(Installer::token(32) !== Installer::token(32));
        $this->assertSame(sha1('s' . sha1('s' . sha1('p'))), Installer::hashPassword('s', 'p'));
    }

    // TEST-ARCH-029: the real core SQL is parseable by the same splitter the installer uses
    public function test_real_core_sql_is_splittable_and_prefixed(): void
    {
        $file = dirname(__DIR__, 3) . '/core/upload/install/opencart.sql';
        $this->assertTrue(is_file($file), 'core opencart.sql present');
        $statements = SqlFile::statements((string) file_get_contents($file));
        $this->assertTrue(count($statements) > 400, 'expected hundreds of statements, got ' . count($statements));
        $creates = array_filter($statements, static function (string $s): bool {
            return strpos($s, 'CREATE TABLE `oc_') === 0;
        });
        $this->assertSame(159, count($creates), 'core defines 159 tables (reference section 9)');
        foreach ($statements as $s) {
            if (!preg_match('/^(DROP TABLE IF EXISTS|CREATE TABLE|INSERT INTO|ALTER TABLE|SET|UPDATE|DELETE FROM)\b/i', $s)) {
                $this->fail('unexpected statement start: ' . substr($s, 0, 60));
            }
        }
    }
}
