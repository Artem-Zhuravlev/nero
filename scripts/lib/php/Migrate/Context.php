<?php
declare(strict_types=1);

namespace Ocs\Migrate;

use Ocs\Migrate\Helper\Events;
use Ocs\Migrate\Helper\Extensions;
use Ocs\Migrate\Helper\Layouts;
use Ocs\Migrate\Helper\Lookup;
use Ocs\Migrate\Helper\Modules;
use Ocs\Migrate\Helper\Ocmod;
use Ocs\Migrate\Helper\Permissions;
use Ocs\Migrate\Helper\SeoUrls;
use Ocs\Migrate\Helper\Settings;

/**
 * Object handed to every .php migration (SPEC-005 REQ-DB-013, REQ-DB-020..030).
 * Every helper is idempotent and logs what it did (created|updated|unchanged).
 */
final class Context
{
    /** @var Db */
    public $db;
    /** @var string */
    public $prefix;
    /** @var string absolute repo root (used to validate event actions) */
    public $repoRoot;
    /** @var callable|null fn(string $app, string $relControllerFile): bool - replaces the repo lookup inside an installed store (SPEC-040) */
    public $routeResolver;
    /** @var string|null directory for system/<code>.ocmod.xml files; null keeps OCMOD in oc_modification (SPEC-053) */
    public $ocmodDir;
    /** @var bool true while running an *_uninstall migration (REQ-DB-012, REQ-DB-030) */
    public $uninstall;
    /** @var bool set by helpers that change OCMOD/events/extensions: refresh.php must run (REQ-DB-040) */
    public $refreshRequired = false;
    /** @var callable|null */
    private $logger;

    /** @var Settings */
    public $setting;
    /** @var Extensions */
    public $extension;
    /** @var Permissions */
    public $permission;
    /** @var Events */
    public $event;
    /** @var SeoUrls */
    public $seoUrl;
    /** @var Layouts */
    public $layout;
    /** @var Modules */
    public $module;
    /** @var Ocmod */
    public $ocmod;
    /** @var Lookup */
    public $lang;
    /** @var Lookup */
    public $currency;
    /** @var Lookup */
    public $group;
    /** @var Lookup */
    public $orderStatus;

    public function __construct(Db $db, string $prefix, string $repoRoot, bool $uninstall = false, ?callable $logger = null)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
            throw new MigrationException('invalid table prefix');
        }
        $this->db = $db;
        $this->prefix = $prefix;
        $this->repoRoot = $repoRoot;
        $this->uninstall = $uninstall;
        $this->logger = $logger;
        $this->setting = new Settings($this);
        $this->extension = new Extensions($this);
        $this->permission = new Permissions($this);
        $this->event = new Events($this);
        $this->seoUrl = new SeoUrls($this);
        $this->layout = new Layouts($this);
        $this->module = new Modules($this);
        $this->ocmod = new Ocmod($this);
        $this->lang = new Lookup($this, 'language', 'language_id', 'code', 'language');
        $this->currency = new Lookup($this, 'currency', 'currency_id', 'code', 'currency');
        $this->group = new Lookup($this, 'user_group', 'user_group_id', 'name', 'user group');
        $this->orderStatus = new Lookup($this, 'order_status', 'order_status_id', 'name', 'order status');
    }

    public function log(string $message): void
    {
        if ($this->logger !== null) {
            ($this->logger)($message);
        }
    }

    /** Full table name with prefix. */
    public function table(string $name): string
    {
        return $this->prefix . $name;
    }

    /** Quotes a PHP value as an SQL literal. */
    public function q($value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            return rtrim(rtrim(sprintf('%.8F', $value), '0'), '.');
        }
        return "'" . $this->db->escape((string) $value) . "'";
    }

    /** Expands {{PREFIX}} and binds ? placeholders (REQ-DB-029). */
    public function bind(string $sql, array $params = []): string
    {
        $sql = str_replace('{{PREFIX}}', $this->prefix, $sql);
        $parts = explode('?', $sql);
        if (count($parts) - 1 !== count($params)) {
            throw new MigrationException('placeholder count does not match parameter count');
        }
        $out = array_shift($parts);
        foreach ($params as $p) {
            $out .= $this->q($p) . array_shift($parts);
        }
        return $out;
    }

    public function sql(string $sql, array $params = []): int
    {
        return $this->db->exec($this->bind($sql, $params));
    }

    /** @return array<int, array<string, mixed>> */
    public function rows(string $sql, array $params = []): array
    {
        return $this->db->rows($this->bind($sql, $params));
    }

    public function query(string $sql, array $params = []): array
    {
        return $this->rows($sql, $params);
    }

    // --- schema helpers: own tables only (REQ-DB-030) ---

    private function ownTable(string $table): string
    {
        $full = strpos($table, $this->prefix) === 0 ? $table : $this->prefix . $table;
        if (strpos($full, $this->prefix . 'ocs_') !== 0) {
            throw new MigrationException("refusing to touch non-custom table {$full} (must start with {$this->prefix}ocs_)");
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $full)) {
            throw new MigrationException("invalid table name {$full}");
        }
        return $full;
    }

    /** $ddl is the full CREATE TABLE statement; must use IF NOT EXISTS and {{PREFIX}}. */
    public function createTable(string $table, string $ddl): void
    {
        $full = $this->ownTable($table);
        if (!preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS/i', $ddl)) {
            throw new MigrationException("createTable({$full}): DDL must use CREATE TABLE IF NOT EXISTS");
        }
        $existed = $this->db->tableExists($full);
        $this->sql($ddl);
        $this->log(($existed ? 'unchanged' : 'created') . " table {$full}");
    }

    public function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        $full = $this->ownTable($table);
        if (!preg_match('/^[A-Za-z0-9_]+$/', $column)) {
            throw new MigrationException('invalid column name');
        }
        if ($this->db->columnExists($full, $column)) {
            $this->log("unchanged column {$full}.{$column}");
            return;
        }
        $this->db->exec("ALTER TABLE `{$full}` ADD COLUMN `{$column}` {$definition}");
        $this->log("created column {$full}.{$column}");
    }

    /** @param string[] $columns */
    public function addIndexIfMissing(string $table, string $index, array $columns): void
    {
        $full = $this->ownTable($table);
        if (!preg_match('/^[A-Za-z0-9_]+$/', $index)) {
            throw new MigrationException('invalid index name');
        }
        if ($this->db->indexExists($full, $index)) {
            $this->log("unchanged index {$full}.{$index}");
            return;
        }
        $cols = implode(', ', array_map(static function (string $c): string {
            return '`' . $c . '`';
        }, $columns));
        $this->db->exec("CREATE INDEX `{$index}` ON `{$full}` ({$cols})");
        $this->log("created index {$full}.{$index}");
    }

    public function dropTableIfExists(string $table): void
    {
        if (!$this->uninstall) {
            throw new MigrationException('dropTableIfExists is allowed only in *_uninstall migrations');
        }
        $full = $this->ownTable($table);
        $this->db->exec("DROP TABLE IF EXISTS `{$full}`");
        $this->log("dropped table {$full}");
    }
}
