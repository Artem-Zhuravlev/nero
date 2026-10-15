<?php
declare(strict_types=1);

namespace Ocs\Migrate;

/** Finds and orders migrations (SPEC-005 REQ-DB-004, REQ-DB-010; SPEC-004 REQ-EXT-004, REQ-EXT-050). */
final class Discovery
{
    /**
     * @return Migration[] in execution order: core-level first, then extensions in dependency order
     */
    public static function all(string $repoRoot): array
    {
        $list = self::core($repoRoot);
        foreach (self::extensionOrder($repoRoot) as $code) {
            $list = array_merge($list, self::extension($repoRoot, $code));
        }
        return $list;
    }

    /** @return Migration[] */
    public static function core(string $repoRoot): array
    {
        $dir = $repoRoot . '/custom/db/migrations';
        $files = is_dir($dir) ? self::files($dir) : [];
        $out = [];
        $expected = 1;
        foreach ($files as $file) {
            if (!preg_match('/^(\d{4})_([a-z0-9_]+)\.(sql|php)$/', $file, $m)) {
                throw new MigrationException("bad core migration file name: {$file} (expected NNNN_slug.sql|php)");
            }
            $n = (int) $m[1];
            if ($n !== $expected) {
                throw new MigrationException(sprintf('core migration numbering: expected %04d, found %s (no gaps or duplicates allowed)', $expected, $m[1]));
            }
            $expected++;
            $out[] = new Migration("core:{$m[1]}_{$m[2]}", $dir . '/' . $file, $m[3], null, $m[1], $m[2]);
        }
        return $out;
    }

    /** @return Migration[] */
    public static function extension(string $repoRoot, string $code): array
    {
        $dir = $repoRoot . '/custom/extensions/' . $code . '/db';
        $files = is_dir($dir) ? self::files($dir) : [];
        $out = [];
        $expected = 1;
        foreach ($files as $file) {
            if (!preg_match('/^(\d{3})_([a-z0-9_]+)\.(sql|php)$/', $file, $m)) {
                throw new MigrationException("bad migration file name in {$code}: {$file} (expected NNN_slug.sql|php)");
            }
            if ((int) $m[1] !== $expected) {
                throw new MigrationException(sprintf('%s migration numbering: expected %03d, found %s', $code, $expected, $m[1]));
            }
            $expected++;
            $out[] = new Migration("ext:{$code}:{$m[1]}_{$m[2]}", $dir . '/' . $file, $m[3], $code, $m[1], $m[2]);
        }
        return $out;
    }

    /** @return string[] sorted file names (no dotfiles) */
    private static function files(string $dir): array
    {
        $names = array_values(array_filter(scandir($dir) ?: [], static function (string $f) use ($dir): bool {
            return $f[0] !== '.' && is_file($dir . '/' . $f);
        }));
        sort($names, SORT_STRING);
        return $names;
    }

    /**
     * Topological order of extensions by extension.json requires.extensions; alphabetical tie-break.
     *
     * @return string[]
     */
    public static function extensionOrder(string $repoRoot): array
    {
        $root = $repoRoot . '/custom/extensions';
        $deps = [];
        if (is_dir($root)) {
            foreach (scandir($root) ?: [] as $code) {
                if ($code[0] === '.' || !is_dir($root . '/' . $code)) {
                    continue;
                }
                $manifest = $root . '/' . $code . '/extension.json';
                $requires = [];
                if (is_file($manifest)) {
                    $json = json_decode((string) file_get_contents($manifest), true);
                    if (!is_array($json)) {
                        throw new MigrationException("invalid JSON in {$code}/extension.json");
                    }
                    $requires = isset($json['requires']['extensions']) && is_array($json['requires']['extensions']) ? $json['requires']['extensions'] : [];
                }
                $deps[$code] = $requires;
            }
        }
        ksort($deps, SORT_STRING);
        foreach ($deps as $code => $requires) {
            foreach ($requires as $r) {
                if (!isset($deps[$r])) {
                    throw new MigrationException("extension {$code} requires missing extension {$r}");
                }
            }
        }
        $ordered = [];
        $state = [];
        $visit = static function (string $code) use (&$visit, &$ordered, &$state, $deps): void {
            if (($state[$code] ?? 0) === 2) {
                return;
            }
            if (($state[$code] ?? 0) === 1) {
                throw new MigrationException("extension dependency cycle at {$code}");
            }
            $state[$code] = 1;
            $reqs = $deps[$code];
            sort($reqs, SORT_STRING);
            foreach ($reqs as $r) {
                $visit($r);
            }
            $state[$code] = 2;
            $ordered[] = $code;
        };
        foreach (array_keys($deps) as $code) {
            $visit($code);
        }
        return $ordered;
    }
}
