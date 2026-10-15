<?php
declare(strict_types=1);

namespace Ocs\Migrate;

/**
 * Db port backed by the store's own DB class (core/upload/system/library/db.php + db/mysqli.php),
 * so charset and session behaviour are identical to the store (SPEC-005 REQ-DB-002).
 * The core files are only read (required) - never modified.
 */
final class CoreDb implements Db
{
    /** @var \DB */
    private $db;
    /** @var string */
    private $schema;

    public function __construct(string $coreLibraryDir, string $host, string $user, string $pass, string $schema, string $port)
    {
        if (!class_exists('DB', false)) {
            require_once $coreLibraryDir . '/db.php';
            require_once $coreLibraryDir . '/db/mysqli.php';
        }
        $this->db = new \DB('mysqli', $host, $user, $pass, $schema, $port);
        $this->schema = $schema;
        $this->db->query("SET time_zone = '" . $this->db->escape(date('P')) . "'");
    }

    public function driver(): string
    {
        return 'mysql';
    }

    public function rows(string $sql): array
    {
        $r = $this->db->query($sql);
        return is_object($r) ? $r->rows : [];
    }

    public function exec(string $sql): int
    {
        $this->db->query($sql);
        return (int) $this->db->countAffected();
    }

    public function escape(string $value): string
    {
        return $this->db->escape($value);
    }

    public function lastId(): int
    {
        return (int) $this->db->getLastId();
    }

    public function tableExists(string $table): bool
    {
        return (bool) $this->rows("SELECT 1 AS x FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . $this->escape($this->schema) . "' AND TABLE_NAME = '" . $this->escape($table) . "'");
    }

    public function columnExists(string $table, string $column): bool
    {
        return (bool) $this->rows("SELECT 1 AS x FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . $this->escape($this->schema) . "' AND TABLE_NAME = '" . $this->escape($table) . "' AND COLUMN_NAME = '" . $this->escape($column) . "'");
    }

    public function indexExists(string $table, string $index): bool
    {
        return (bool) $this->rows("SELECT 1 AS x FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = '" . $this->escape($this->schema) . "' AND TABLE_NAME = '" . $this->escape($table) . "' AND INDEX_NAME = '" . $this->escape($index) . "'");
    }

    public function ensureMigrationTable(string $table): void
    {
        $this->exec('CREATE TABLE IF NOT EXISTS `' . $table . '` ('
            . ' `id` VARCHAR(96) NOT NULL,'
            . ' `checksum` CHAR(64) NOT NULL,'
            . ' `applied_at` DATETIME NOT NULL,'
            . ' `duration_ms` INT NOT NULL,'
            . " `status` ENUM('applied','failed') NOT NULL DEFAULT 'applied',"
            . " `note` VARCHAR(255) NOT NULL DEFAULT '',"
            . ' PRIMARY KEY (`id`)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8');
    }

    public function lock(string $name): bool
    {
        $r = $this->rows("SELECT GET_LOCK('" . $this->escape($name) . "', 0) AS l");
        return isset($r[0]['l']) && (int) $r[0]['l'] === 1;
    }

    public function unlock(string $name): void
    {
        $this->rows("SELECT RELEASE_LOCK('" . $this->escape($name) . "') AS l");
    }
}
