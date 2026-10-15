<?php
declare(strict_types=1);

namespace Ocs\Test\Support;

use Ocs\Migrate\Db;

/** In-memory SQLite adapter for L1/L2-lite tests of runner, helpers and installer (no MySQL needed). */
final class SqliteDb implements Db
{
    /** @var \PDO */
    public $pdo;
    /** @var array<string, bool> */
    public static $locks = [];
    /** @var string[] executed statements (for assertions) */
    public $log = [];

    public function __construct()
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        self::$locks = [];
    }

    public function driver(): string
    {
        return 'sqlite';
    }

    public function rows(string $sql): array
    {
        $this->log[] = $sql;
        return $this->pdo->query($sql)->fetchAll();
    }

    public function exec(string $sql): int
    {
        $this->log[] = $sql;
        return (int) $this->pdo->exec($sql);
    }

    public function escape(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    public function lastId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function tableExists(string $table): bool
    {
        return (bool) $this->pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='" . $this->escape($table) . "'")->fetch();
    }

    public function columnExists(string $table, string $column): bool
    {
        foreach ($this->pdo->query('PRAGMA table_info(`' . $table . '`)')->fetchAll() as $c) {
            if ($c['name'] === $column) {
                return true;
            }
        }
        return false;
    }

    public function indexExists(string $table, string $index): bool
    {
        return (bool) $this->pdo->query("SELECT 1 FROM sqlite_master WHERE type='index' AND tbl_name='" . $this->escape($table) . "' AND name='" . $this->escape($index) . "'")->fetch();
    }

    public function ensureMigrationTable(string $table): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS `' . $table . '` (id TEXT PRIMARY KEY, checksum TEXT NOT NULL, applied_at TEXT NOT NULL, duration_ms INTEGER NOT NULL, status TEXT NOT NULL DEFAULT \'applied\', note TEXT NOT NULL DEFAULT \'\')');
    }

    public function lock(string $name): bool
    {
        if (!empty(self::$locks[$name])) {
            return false;
        }
        self::$locks[$name] = true;
        return true;
    }

    public function unlock(string $name): void
    {
        unset(self::$locks[$name]);
    }

    /** Creates the small subset of core tables that helpers and installer touch (SQLite flavour). */
    public function createCoreSubset(string $p = 'oc_'): void
    {
        $ddl = [
            "CREATE TABLE {$p}setting (setting_id INTEGER PRIMARY KEY AUTOINCREMENT, store_id INTEGER NOT NULL DEFAULT 0, code TEXT NOT NULL, `key` TEXT NOT NULL, `value` TEXT NOT NULL, serialized INTEGER NOT NULL DEFAULT 0)",
            "CREATE TABLE {$p}extension (extension_id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT NOT NULL, code TEXT NOT NULL)",
            "CREATE TABLE {$p}event (event_id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL, `trigger` TEXT NOT NULL, `action` TEXT NOT NULL, `status` INTEGER NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0)",
            "CREATE TABLE {$p}user_group (user_group_id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, permission TEXT NOT NULL)",
            "CREATE TABLE {$p}seo_url (seo_url_id INTEGER PRIMARY KEY AUTOINCREMENT, store_id INTEGER NOT NULL, language_id INTEGER NOT NULL, `query` TEXT NOT NULL, keyword TEXT NOT NULL)",
            "CREATE TABLE {$p}language (language_id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, code TEXT NOT NULL, status INTEGER NOT NULL DEFAULT 1)",
            "CREATE TABLE {$p}layout_route (layout_route_id INTEGER PRIMARY KEY AUTOINCREMENT, layout_id INTEGER NOT NULL, store_id INTEGER NOT NULL, route TEXT NOT NULL)",
            "CREATE TABLE {$p}layout_module (layout_module_id INTEGER PRIMARY KEY AUTOINCREMENT, layout_id INTEGER NOT NULL, code TEXT NOT NULL, `position` TEXT NOT NULL, sort_order INTEGER NOT NULL)",
            "CREATE TABLE {$p}module (module_id INTEGER PRIMARY KEY AUTOINCREMENT, `name` TEXT NOT NULL, code TEXT NOT NULL, setting TEXT NOT NULL)",
            "CREATE TABLE {$p}modification (modification_id INTEGER PRIMARY KEY AUTOINCREMENT, extension_install_id INTEGER NOT NULL, name TEXT NOT NULL, code TEXT NOT NULL, author TEXT NOT NULL, version TEXT NOT NULL, link TEXT NOT NULL, xml TEXT NOT NULL, status INTEGER NOT NULL, date_added TEXT NOT NULL)",
            "CREATE TABLE {$p}currency (currency_id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL)",
            "CREATE TABLE {$p}order_status (order_status_id INTEGER NOT NULL, language_id INTEGER NOT NULL, name TEXT NOT NULL)",
        ];
        foreach ($ddl as $sql) {
            $this->pdo->exec($sql);
        }
        $this->pdo->exec("INSERT INTO {$p}language (name, code) VALUES ('Ukrainian', 'uk-ua'), ('English', 'en-gb')");
        $this->pdo->exec("INSERT INTO {$p}user_group (user_group_id, name, permission) VALUES (1, 'Administrator', '{\"access\":[\"common/dashboard\"],\"modify\":[\"common/dashboard\"]}'), (10, 'Demonstration', '')");
        $this->pdo->exec("INSERT INTO {$p}currency (code) VALUES ('UAH')");
        $this->pdo->exec("INSERT INTO {$p}order_status (order_status_id, language_id, name) VALUES (1, 1, 'Очікування'), (1, 2, 'Pending')");
    }
}
