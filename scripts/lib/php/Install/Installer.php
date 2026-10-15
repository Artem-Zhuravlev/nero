<?php
declare(strict_types=1);

namespace Ocs\Install;

use Ocs\Migrate\Db;
use Ocs\Migrate\MigrationException;
use Ocs\Migrate\SqlFile;

/**
 * Replays the database steps of the stock installer (core/upload/install/model/install/install.php and
 * cli_install.php) against the target DB, using core/upload/install/opencart.sql as data.
 * The stock installers are never run against core/ because they write config files into it
 * (SPEC-001 REQ-ARCH-032). Config files come from the build (SPEC-003).
 */
final class Installer
{
    /** @var Db */
    private $db;
    /** @var callable */
    private $say;

    public function __construct(Db $db, ?callable $say = null)
    {
        $this->db = $db;
        $this->say = $say ?? static function (string $m): void {
            fwrite(STDOUT, $m . "\n");
        };
    }

    /** Random alphanumeric token, like core's token() helper. */
    public static function token(int $length): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $out = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[random_int(0, $max)];
        }
        return $out;
    }

    /** Core's admin password scheme (reference section 2). */
    public static function hashPassword(string $salt, string $password): string
    {
        return sha1($salt . sha1($salt . sha1($password)));
    }

    public function isInstalled(string $prefix): bool
    {
        return $this->db->tableExists($prefix . 'setting');
    }

    /**
     * @param array{prefix:string, sqlFile:string, username:string, email:string, password:string, force?:bool, year?:string, now?:string} $o
     */
    public function install(array $o): void
    {
        $prefix = $o['prefix'];
        if (!preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
            throw new MigrationException('invalid table prefix');
        }
        if (!is_file($o['sqlFile'])) {
            throw new MigrationException('installer SQL not found: ' . $o['sqlFile']);
        }
        if ($this->isInstalled($prefix) && empty($o['force'])) {
            throw new MigrationException('store already installed (table ' . $prefix . 'setting exists); use --force to re-import (destroys data)');
        }
        $now = $o['now'] ?? date('Y-m-d H:i:s');
        $year = $o['year'] ?? date('Y');

        ($this->say)('importing opencart.sql');
        $sql = (string) file_get_contents($o['sqlFile']);
        foreach (['DROP TABLE IF EXISTS `oc_', 'CREATE TABLE `oc_', 'INSERT INTO `oc_'] as $needle) {
            $sql = str_replace($needle, substr($needle, 0, -3) . $prefix, $sql);
        }
        $count = 0;
        foreach (SqlFile::statements($sql) as $st) {
            $this->db->exec($st);
            $count++;
        }
        ($this->say)("executed {$count} statements");

        if ($this->db->driver() === 'mysql') {
            $this->db->exec("SET @@session.sql_mode = ''");
        }

        $q = function (string $v): string {
            return "'" . $this->db->escape($v) . "'";
        };
        $user = $prefix . 'user';
        $setting = $prefix . 'setting';

        $this->db->exec("DELETE FROM `{$user}` WHERE user_id = '1'");
        $salt = self::token(9);
        $this->db->exec("INSERT INTO `{$user}` (user_id, user_group_id, username, salt, password, firstname, lastname, email, status, date_added) VALUES ('1', '1', "
            . $q($o['username']) . ', ' . $q($salt) . ', ' . $q(self::hashPassword($salt, $o['password'])) . ", 'John', 'Doe', " . $q($o['email']) . ", '1', " . $q($now) . ')');

        $this->setSetting($setting, 'config_email', $o['email']);
        $this->setSetting($setting, 'config_encryption', self::token(1024));
        $this->db->exec('UPDATE `' . $prefix . "product` SET `viewed` = '0'");

        $this->db->exec('INSERT INTO `' . $prefix . "api` (username, `key`, status, date_added, date_modified) VALUES ('Default', " . $q(self::token(256)) . ", 1, " . $q($now) . ', ' . $q($now) . ')');
        $apiId = $this->db->lastId();
        $this->setSetting($setting, 'config_api_id', (string) $apiId);
        $this->db->exec("UPDATE `{$setting}` SET `value` = " . $q("INV-{$year}-00") . " WHERE `key` = 'config_invoice_prefix'");
        ($this->say)('install ok (admin user #1 created)');
    }

    private function setSetting(string $table, string $key, string $value): void
    {
        $this->db->exec("DELETE FROM `{$table}` WHERE `key` = '" . $this->db->escape($key) . "'");
        $this->db->exec("INSERT INTO `{$table}` (store_id, `code`, `key`, `value`, serialized) VALUES (0, 'config', '" . $this->db->escape($key) . "', '" . $this->db->escape($value) . "', 0)");
    }
}
