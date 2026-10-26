<?php
declare(strict_types=1);

namespace Ocs\Migrate;

/**
 * Migration runner (SPEC-005 REQ-DB-001..009). Not atomic by design (MyISAM): every migration must be
 * idempotent; a failure is recorded and the run stops; the next run re-executes failed migrations.
 */
final class Runner
{
    public const EXIT_OK = 0;
    public const EXIT_FAILED = 1;
    public const EXIT_CHECKSUM = 3;
    public const EXIT_LOCKED = 4;

    /** @var Db */
    private $db;
    /** @var string */
    private $prefix;
    /** @var string */
    private $root;
    /** @var callable */
    private $out;
    /** @var bool */
    public $refreshRequired = false;
    /** @var callable|null see Context::$routeResolver */
    public $routeResolver;

    public function __construct(Db $db, string $prefix, string $repoRoot, ?callable $out = null)
    {
        $this->db = $db;
        $this->prefix = $prefix;
        $this->root = rtrim($repoRoot, '/\\');
        $this->out = $out ?? static function (string $line): void {
            fwrite(STDOUT, $line . "\n");
        };
    }

    private function say(string $line): void
    {
        ($this->out)($line);
    }

    private function table(): string
    {
        return $this->prefix . 'ocs_migration';
    }

    /** @return array<string, array<string, mixed>> id => row */
    private function applied(): array
    {
        if (!$this->db->tableExists($this->table())) {
            return [];
        }
        $map = [];
        foreach ($this->db->rows('SELECT id, checksum, status, note FROM `' . $this->table() . '`') as $row) {
            $map[(string) $row['id']] = $row;
        }
        return $map;
    }

    /**
     * @return array<int, array{id:string, state:string}> states: applied|pending|changed|failed
     */
    public function status(): array
    {
        $applied = $this->applied();
        $out = [];
        foreach (Discovery::all($this->root) as $m) {
            $row = $applied[$m->id] ?? null;
            if ($row === null) {
                $state = 'pending';
            } elseif ((string) $row['status'] === 'failed') {
                $state = 'failed';
            } elseif ((string) $row['checksum'] !== $m->checksum()) {
                $state = 'changed';
            } else {
                $state = 'applied';
            }
            $out[] = ['id' => $m->id, 'state' => $state];
        }
        return $out;
    }

    /** @return string[] lint problems keyed by migration id */
    public function preflight(Migration $m): array
    {
        $src = (string) file_get_contents($m->path);
        $problems = $m->type === 'sql' ? SqlFile::lint($src, $m->isUninstall()) : SqlFile::lintPhp($src);
        return array_map(static function (string $p) use ($m): string {
            return $m->id . ': ' . $p;
        }, $problems);
    }

    private function recentBackupExists(): bool
    {
        foreach (glob($this->root . '/var/backups/*/manifest.json') ?: [] as $file) {
            if (filemtime($file) >= time() - 86400) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array{dryRun?:bool, to?:string, requireBackup?:bool, acceptChecksum?:string[]} $opt
     * @return int exit code
     */
    public function up(array $opt = []): int
    {
        $dry = !empty($opt['dryRun']);
        try {
            $all = Discovery::all($this->root);
        } catch (MigrationException $e) {
            $this->say('FAIL discovery: ' . $e->getMessage());
            return self::EXIT_CHECKSUM;
        }

        $problems = [];
        foreach ($all as $m) {
            $problems = array_merge($problems, $this->preflight($m));
        }
        if ($problems) {
            foreach ($problems as $p) {
                $this->say('FAIL lint ' . $p);
            }
            return self::EXIT_CHECKSUM;
        }

        $applied = $this->applied();
        foreach ($all as $m) {
            $row = $applied[$m->id] ?? null;
            if ($row !== null && (string) $row['status'] === 'applied' && (string) $row['checksum'] !== $m->checksum()
                && !in_array($m->id, $opt['acceptChecksum'] ?? [], true)) {
                $this->say("FAIL checksum mismatch for applied migration {$m->id} (applied migrations must never be edited; add a new one)");
                return self::EXIT_CHECKSUM;
            }
        }

        $pending = array_values(array_filter($all, static function (Migration $m) use ($applied, $opt): bool {
            $row = $applied[$m->id] ?? null;
            if ($row !== null && (string) $row['status'] === 'applied') {
                if (in_array($m->id, $opt['acceptChecksum'] ?? [], true) && (string) $row['checksum'] !== $m->checksum()) {
                    return true;
                }
                return false;
            }
            return !$m->isUninstall();
        }));
        if (!empty($opt['to'])) {
            $cut = array_search($opt['to'], array_map(static function (Migration $m): string {
                return $m->id;
            }, $pending), true);
            if ($cut === false) {
                $this->say("FAIL --to {$opt['to']}: not a pending migration");
                return self::EXIT_FAILED;
            }
            $pending = array_slice($pending, 0, $cut + 1);
        }

        if ($dry) {
            $this->say('dry-run: ' . count($pending) . ' pending migration(s)');
            foreach ($pending as $m) {
                $this->say("  would apply {$m->id}");
                if ($m->type === 'sql') {
                    foreach (SqlFile::statements((string) file_get_contents($m->path)) as $st) {
                        $this->say('    ' . preg_replace('/\s+/', ' ', $st));
                    }
                }
            }
            return self::EXIT_OK;
        }

        if (!empty($opt['requireBackup']) && $pending && !$this->recentBackupExists()) {
            $this->say('FAIL no backup newer than 24h found in var/backups (use --no-backup-check to override)');
            return self::EXIT_FAILED;
        }

        $this->db->ensureMigrationTable($this->table());
        if (!$this->db->lock('ocs_migrate')) {
            $this->say('FAIL another migration runner holds the lock');
            return self::EXIT_LOCKED;
        }
        try {
            if (!$pending) {
                $this->say('nothing to do');
                return self::EXIT_OK;
            }
            foreach ($pending as $m) {
                if (!$this->apply($m)) {
                    return self::EXIT_FAILED;
                }
            }
            $this->say('migrate ok: ' . count($pending) . ' applied' . ($this->refreshRequired ? ' (refresh required: run scripts/refresh.php)' : ''));
            return self::EXIT_OK;
        } finally {
            $this->db->unlock('ocs_migrate');
        }
    }

    private function newContext(bool $uninstall): Context
    {
        $ctx = new Context($this->db, $this->prefix, $this->root, $uninstall, function (string $m): void {
            $this->say('    ' . $m);
        });
        $ctx->routeResolver = $this->routeResolver;
        return $ctx;
    }

    private function apply(Migration $m, bool $record = true): bool
    {
        $this->say("apply {$m->id}");
        $started = microtime(true);
        $ctx = $this->newContext($m->isUninstall());
        try {
            if ($m->type === 'sql') {
                foreach (SqlFile::statements((string) file_get_contents($m->path)) as $st) {
                    $this->db->exec(SqlFile::expand($st, $ctx));
                }
            } else {
                $fn = (static function (string $__file) {
                    return require $__file;
                })($m->path);
                if (!is_callable($fn)) {
                    throw new MigrationException('migration file did not return a callable');
                }
                $fn($ctx);
            }
        } catch (\Throwable $e) {
            $note = substr(str_replace(["\r", "\n"], ' ', $e->getMessage()), 0, 255);
            if ($record) {
                $this->record($m, 'failed', $note, (int) round((microtime(true) - $started) * 1000));
            }
            $this->say("FAIL {$m->id}: {$note}");
            return false;
        }
        if ($ctx->refreshRequired) {
            $this->refreshRequired = true;
        }
        if ($record) {
            $this->record($m, 'applied', '', (int) round((microtime(true) - $started) * 1000));
        }
        return true;
    }

    private function record(Migration $m, string $status, string $note, int $ms): void
    {
        $ctx = $this->newContext(false);
        $t = '`' . $this->table() . '`';
        $ctx->sql("DELETE FROM {$t} WHERE id = ?", [$m->id]);
        $ctx->sql(
            "INSERT INTO {$t} (id, checksum, applied_at, duration_ms, `status`, note) VALUES (?, ?, ?, ?, ?, ?)",
            [$m->id, $m->checksum(), date('Y-m-d H:i:s'), $ms, $status, $note]
        );
    }

    /** Runs an extension's *_uninstall migration and forgets its bookkeeping rows (REQ-DB-001, REQ-EXT-050). */
    public function down(string $extCode): int
    {
        $mine = Discovery::extension($this->root, $extCode);
        $un = array_values(array_filter($mine, static function (Migration $m): bool {
            return $m->isUninstall();
        }));
        if (!$un) {
            $this->say("FAIL extension {$extCode} has no *_uninstall migration");
            return self::EXIT_FAILED;
        }
        foreach ($un as $m) {
            $problems = $this->preflight($m);
            if ($problems) {
                foreach ($problems as $p) {
                    $this->say('FAIL lint ' . $p);
                }
                return self::EXIT_CHECKSUM;
            }
        }
        $this->db->ensureMigrationTable($this->table());
        if (!$this->db->lock('ocs_migrate')) {
            $this->say('FAIL another migration runner holds the lock');
            return self::EXIT_LOCKED;
        }
        try {
            foreach ($un as $m) {
                if (!$this->apply($m, false)) {
                    return self::EXIT_FAILED;
                }
            }
            $ctx = $this->newContext(false);
            $ctx->sql('DELETE FROM `' . $this->table() . '` WHERE id LIKE ?', ["ext:{$extCode}:%"]);
            $this->say("down ok: {$extCode}");
            return self::EXIT_OK;
        } finally {
            $this->db->unlock('ocs_migrate');
        }
    }
}
