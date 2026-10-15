<?php
declare(strict_types=1);

namespace Ocs\Migrate;

/** SQL migration parsing, token expansion and linting (SPEC-005 REQ-DB-011, REQ-DB-012). */
final class SqlFile
{
    /**
     * Same approach as the core installer: skip blank and comment lines, a statement ends with ';' at line end.
     *
     * @return string[]
     */
    public static function statements(string $sql): array
    {
        $out = [];
        $buf = '';
        foreach (preg_split('/\R/', $sql) ?: [] as $raw) {
            $line = trim($raw);
            if ($line === '' || strncmp($line, '--', 2) === 0 || $line[0] === '#') {
                continue;
            }
            $buf .= ($buf === '' ? '' : "\n") . $line;
            if (preg_match('/;\s*$/', $line)) {
                $out[] = rtrim($buf, "; \t\n");
                $buf = '';
            }
        }
        if (trim($buf) !== '') {
            $out[] = rtrim($buf, "; \t\n");
        }
        return $out;
    }

    /** Expands {{PREFIX}}, {{STORE_ID}}, {{LANG:code}}, {{GROUP:name}}; unknown tokens are errors. */
    public static function expand(string $sql, Context $ctx, int $store = 0): string
    {
        return (string) preg_replace_callback('/\{\{([A-Z_]+)(?::([^}]+))?\}\}/', static function (array $m) use ($ctx, $store): string {
            switch ($m[1]) {
                case 'PREFIX':
                    return $ctx->prefix;
                case 'STORE_ID':
                    return (string) $store;
                case 'LANG':
                    return (string) $ctx->lang->id($m[2] ?? '');
                case 'GROUP':
                    return (string) $ctx->group->id($m[2] ?? '');
                default:
                    throw new MigrationException('unknown SQL token ' . $m[0]);
            }
        }, $sql);
    }

    /**
     * @return string[] problems (empty = ok)
     */
    public static function lint(string $sql, bool $uninstall): array
    {
        $problems = [];
        if (strpos($sql, "\r") !== false) {
            $problems[] = 'file must use LF line endings';
        }
        if (strncmp($sql, "\xEF\xBB\xBF", 3) === 0) {
            $problems[] = 'file must not start with a BOM';
        }
        if (preg_match('/^\s*DELIMITER\b/mi', $sql)) {
            $problems[] = 'DELIMITER blocks are not allowed';
        }
        foreach (self::statements($sql) as $i => $st) {
            $n = $i + 1;
            $flat = strtoupper(preg_replace('/\s+/', ' ', $st));
            if (!$uninstall && preg_match('/^(DROP|TRUNCATE)\b/', $flat)) {
                $problems[] = "statement {$n}: DROP/TRUNCATE only allowed in *_uninstall migrations";
            }
            if (!$uninstall && preg_match('/^DELETE FROM [^ ]+( |$)/', $flat) && strpos($flat, ' WHERE ') === false) {
                $problems[] = "statement {$n}: DELETE without WHERE only allowed in *_uninstall migrations";
            }
            if (preg_match('/(?<![A-Za-z0-9_])oc_[a-z_]+/', $st)) {
                $problems[] = "statement {$n}: hard-coded table prefix 'oc_' (use {{PREFIX}})";
            }
            if (preg_match('/^(CREATE (?:UNIQUE )?TABLE|ALTER TABLE|DROP TABLE|RENAME TABLE)\s+(?:IF (?:NOT )?EXISTS\s+)?`?([^\s`(]+)`?/i', $st, $m)
                && strpos($m[2], '{{PREFIX}}ocs_') !== 0) {
                $problems[] = "statement {$n}: DDL on '{$m[2]}' - only {{PREFIX}}ocs_* tables may be changed (core tables are never altered)";
            }
            if (preg_match('/^CREATE (?:UNIQUE )?INDEX\s+`?[^\s`]+`?\s+ON\s+`?([^\s`(]+)`?/i', $st, $m)
                && strpos($m[1], '{{PREFIX}}ocs_') !== 0) {
                $problems[] = "statement {$n}: index on '{$m[1]}' - only {{PREFIX}}ocs_* tables may be changed";
            }
            if (preg_match('/^INSERT INTO\s+`?\{\{PREFIX\}\}setting`?\b/i', $st)) {
                $problems[] = "statement {$n}: raw INSERT into setting (no unique key) - use \$m->setting->set() in a .php migration";
            }
        }
        return $problems;
    }

    /** Static checks for .php migrations (REQ-DB-013). @return string[] */
    public static function lintPhp(string $php): array
    {
        $problems = [];
        if (!preg_match('/declare\s*\(\s*strict_types\s*=\s*1\s*\)/', $php)) {
            $problems[] = 'missing declare(strict_types=1)';
        }
        if (preg_match('/\b(exit|die)\b\s*[;(]/', $php)) {
            $problems[] = 'exit/die are not allowed';
        }
        if (preg_match('/\b(echo|print)\b/', $php)) {
            $problems[] = 'echo/print are not allowed (use $m->log())';
        }
        if (!preg_match('/return\s+(static\s+)?function\s*\(/', $php)) {
            $problems[] = 'must return a closure: return function (\\Ocs\\Migrate\\Context $m): void { ... };';
        }
        return $problems;
    }
}
