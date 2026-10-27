<?php
declare(strict_types=1);

namespace Ocs\Setup;

use Ocs\Migrate\Context;
use Ocs\Migrate\Db;
use Ocs\Migrate\Runner;

/**
 * Install/activate/uninstall logic of the packaged theme (SPEC-040). Runs inside an existing OpenCart/ocStore admin request
 * (the same migration runner as `npm run db:migrate`, fed from the package payload) and is unit-testable on SQLite.
 */
final class Service
{
    public const REQUIRED_TABLES = ['setting', 'extension', 'event', 'modification', 'layout', 'layout_module', 'module', 'language', 'user_group', 'category', 'product'];

    /** @var Db */
    private $db;
    /** @var string */
    private $prefix;
    /** @var string payload root: contains custom/db, custom/extensions/*, custom/ocmod and VERSION */
    private $payload;
    /** @var callable|null */
    private $routeResolver;
    /** @var string[] */
    private $log = [];

    /** @var string|null system/ directory that receives the ocs_*.ocmod.xml files (SPEC-053) */
    private $systemDir;

    public function __construct(Db $db, string $prefix, string $payloadRoot, ?callable $routeResolver = null, ?string $systemDir = null)
    {
        $this->systemDir = $systemDir !== null ? rtrim($systemDir, '/\\') : null;
        $this->db = $db;
        $this->prefix = $prefix;
        $this->payload = rtrim($payloadRoot, '/\\');
        $this->routeResolver = $routeResolver;
    }

    public function version(): string
    {
        $f = $this->payload . '/VERSION';
        return is_file($f) ? trim((string) file_get_contents($f)) : '0.0.0';
    }

    /** @return string[] */
    public function log(): array
    {
        return $this->log;
    }

    private function say(string $line): void
    {
        if (strpos($line, DemoCatalogue::SETTING_KEY) !== false) {
            return; // registry bookkeeping, one line per created record
        }
        // The runner hints at the CLI refresh script; inside the store the setup page refreshes modifications itself.
        $this->log[] = str_replace(' (refresh required: run scripts/refresh.php)', ' (modifications will be refreshed)', $line);
    }

    /**
     * @param array{core_version?:string, php_version?:string, writable?:array<string,bool>} $env
     * @return string[] human readable problems; empty when installation can proceed (REQ-PKG-007)
     */
    public function precheck(array $env = []): array
    {
        $problems = [];
        if (isset($env['core_version']) && version_compare($env['core_version'], '3.0.3.0', '<')) {
            $problems[] = 'OpenCart/ocStore ' . $env['core_version'] . ' is too old; 3.0.3.x is required';
        }
        if (isset($env['core_version']) && version_compare($env['core_version'], '4.0.0.0', '>=')) {
            $problems[] = 'OpenCart ' . $env['core_version'] . ' is not supported; 3.0.3.x is required';
        }
        if (isset($env['php_version']) && version_compare($env['php_version'], '7.1.0', '<')) {
            $problems[] = 'PHP ' . $env['php_version'] . ' is too old; PHP 7.1+ is required';
        }
        foreach (self::REQUIRED_TABLES as $t) {
            if (!$this->db->tableExists($this->prefix . $t)) {
                $problems[] = "required table is missing: {$this->prefix}{$t}";
            }
        }
        foreach ($env['writable'] ?? [] as $path => $ok) {
            if (!$ok) {
                $problems[] = "not writable: {$path}";
            }
        }
        if ($this->systemDir !== null && !is_writable($this->systemDir)) {
            $problems[] = "not writable: {$this->systemDir}";
        }
        if (!is_dir($this->payload . '/custom/db/migrations')) {
            $problems[] = 'package payload is incomplete (custom/db/migrations not found)';
        }
        return $problems;
    }

    /** @return array<int, array{0:string, 1:string}> [setting code, key] of every module_ocs_*_status setting */
    private function moduleStatusKeys(): array
    {
        $out = [];
        foreach ($this->db->rows('SELECT DISTINCT `code`, `key` FROM `' . $this->prefix . "setting` WHERE `key` LIKE 'module_ocs%'") as $row) {
            if (preg_match('/^module_ocs_[a-z0-9_]+_status$/', (string) $row['key'])) {
                $out[] = [(string) $row['code'], (string) $row['key']];
            }
        }
        return $out;
    }

    private function runner(): Runner
    {
        $r = new Runner($this->db, $this->prefix, $this->payload, function (string $line): void {
            $this->say($line);
        });
        $r->routeResolver = $this->routeResolver;
        $r->ocmodDir = $this->systemDir;
        return $r;
    }

    private function context(): Context
    {
        $ctx = new Context($this->db, $this->prefix, $this->payload, false, function (string $line): void {
            $this->say($line);
        });
        $ctx->ocmodDir = $this->systemDir;
        return $ctx;
    }

    /**
     * Runs pending migrations. Does not change the active storefront theme (REQ-PKG-006).
     *
     * @param array{core_version?:string, php_version?:string, writable?:array<string,bool>} $env
     * @return array{ok:bool, problems:string[], log:string[], refresh:bool}
     */
    public function install(array $env = []): array
    {
        $this->log = [];
        $problems = $this->precheck($env);
        if ($problems) {
            return ['ok' => false, 'problems' => $problems, 'log' => $this->log, 'refresh' => false];
        }
        $m = $this->context();
        $before = $m->setting->get('config', 'config_theme');

        $runner = $this->runner();
        $code = $runner->up();
        $resynced = $code === Runner::EXIT_OK && $this->syncOcmodFiles($m);

        // Migration 0001 selects the theme for the dev stand; a package install must never switch a live storefront.
        $after = $m->setting->get('config', 'config_theme');
        if ($before !== null && (string) $after !== (string) $before) {
            $m->setting->set('config', 'config_theme', (string) $before);
            $this->say("kept active theme '{$before}' (use Activate to switch)");
        } elseif ($before === null && $after !== null) {
            $this->db->exec('DELETE FROM `' . $this->prefix . "setting` WHERE store_id = 0 AND `code` = 'config' AND `key` = 'config_theme'");
        }
        if ($code === Runner::EXIT_OK) {
            $m->setting->set('ocs_package', 'ocs_package_version', $this->version());
        }
        return ['ok' => $code === Runner::EXIT_OK, 'problems' => [], 'log' => $this->log, 'refresh' => $runner->refreshRequired || $resynced];
    }

    /**
     * SPEC-053 REQ-OCM-001/010: every packaged OCMOD is (re)written to system/ on each install, even when its migration is already
     * logged, so modifications deleted by hand come back. Leftover oc_modification rows are dropped (they would apply twice).
     */
    private function syncOcmodFiles(Context $m): bool
    {
        if ($this->systemDir === null) {
            return false;
        }
        $changed = false;
        foreach ((array) glob($this->payload . '/custom/ocmod/ocs_*.ocmod.xml') as $xmlPath) {
            $target = $this->systemDir . '/' . basename($xmlPath);
            $same = is_file($target) && (string) file_get_contents($target) === (string) file_get_contents($xmlPath);
            $m->ocmod->install($xmlPath);
            $changed = $changed || !$same;
        }
        $t = '`' . $this->prefix . 'modification`';
        foreach ($this->db->rows("SELECT modification_id AS id FROM {$t} WHERE `code` LIKE 'ocs_%'") as $row) {
            $this->db->exec("DELETE FROM {$t} WHERE modification_id = " . (int) $row['id']);
            $changed = true;
        }
        return $changed;
    }

    /** @return array<int, array{id:string, state:string}> */
    public function status(): array
    {
        return $this->runner()->status();
    }

    /**
     * Selects the theme and switches every ocs module on (REQ-PKG-006).
     *
     * @return array{ok:bool, log:string[]}
     */
    public function activate(): array
    {
        $this->log = [];
        $m = $this->context();
        $m->setting->set('theme_ocs', 'theme_ocs_status', 1);
        $m->setting->set('config', 'config_theme', 'ocs');
        $n = 0;
        foreach ($this->moduleStatusKeys() as [$code, $key]) {
            $m->setting->set($code, $key, 1);
            $n++;
        }
        $this->say("theme ocs selected, {$n} module(s) switched on");
        return ['ok' => true, 'log' => $this->log];
    }

    /**
     * Non-destructive removal from the storefront: tables and data stay (REQ-PKG-010).
     *
     * @return array{ok:bool, log:string[], refresh:bool}
     */
    public function uninstall(): array
    {
        $this->log = [];
        $p = $this->prefix;
        $this->db->exec('UPDATE `' . $p . "setting` SET `value` = 'default' WHERE `key` = 'config_theme' AND `value` = 'ocs'");
        $m = $this->context();
        foreach ($this->moduleStatusKeys() as [$code, $key]) {
            $m->setting->set($code, $key, 0);
        }
        foreach (['event' => 'event_id', 'modification' => 'modification_id'] as $table => $pk) {
            foreach ($this->db->rows("SELECT {$pk} AS id, `code` FROM `" . $p . "{$table}` WHERE `code` LIKE 'ocs%'") as $row) {
                if (strncmp((string) $row['code'], 'ocs_', 4) === 0) {
                    $this->db->exec('DELETE FROM `' . $p . "{$table}` WHERE {$pk} = " . (int) $row['id']);
                }
            }
        }
        if ($this->systemDir !== null) {
            foreach ((array) glob($this->systemDir . '/ocs_*.ocmod.xml') as $file) {
                @unlink($file);
            }
        }
        if ($this->db->tableExists($p . 'ocs_migration')) {
            $this->db->exec('DELETE FROM `' . $p . 'ocs_migration`');
        }
        $this->say('theme set back to default, ocs modules switched off, events and modifications removed, data tables kept');
        return ['ok' => true, 'log' => $this->log, 'refresh' => true];
    }

    /**
     * @param string|null $imageDir docroot image dir with trailing slash
     * @return array{ok:bool, log:string[]}
     */
    public function demo(?string $imageDir = null, ?callable $models = null): array
    {
        $this->log = [];
        $m = $this->context();
        $hints = [];
        if ($models !== null && is_file($this->catalogueFile())) {
            $cat = $this->catalogue($m, $models, (string) $imageDir);
            $hints = $cat->exists() ? $cat->homeHints() : [];
        }
        (new DemoSeed($m, function (string $line): void {
            $this->say($line);
        }, $imageDir, $hints))->run();
        return ['ok' => true, 'log' => $this->log];
    }

    private function catalogueFile(): string
    {
        return $this->payload . '/custom/catalog/demo/catalogue.json';
    }

    private function catalogue(Context $m, callable $models, string $imageDir): DemoCatalogue
    {
        return new DemoCatalogue($m, DemoCatalogue::load($this->catalogueFile()), $models, $imageDir, function (string $line): void {
            $this->say($line);
        });
    }

    /**
     * SPEC-041: creates the demo catalogue through the core admin models.
     *
     * @param callable $models fn(string $route): object
     * @return array{ok:bool, log:string[], refresh:bool}
     */
    public function createCatalogue(callable $models, string $imageDir): array
    {
        $this->log = [];
        $r = $this->catalogue($this->context(), $models, $imageDir)->create();
        $this->say("demo catalogue ready: {$r['products']} new product(s), {$r['categories']} categories");
        return ['ok' => true, 'log' => $this->log, 'refresh' => false];
    }

    /** @return array{ok:bool, log:string[], refresh:bool} */
    public function removeCatalogue(callable $models, string $imageDir): array
    {
        $this->log = [];
        $this->catalogue($this->context(), $models, $imageDir)->remove();
        return ['ok' => true, 'log' => $this->log, 'refresh' => false];
    }
}
