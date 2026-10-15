<?php
declare(strict_types=1);

// Shared CLI bootstrap for scripts/migrate.php and scripts/install.php.

require_once __DIR__ . '/autoload.php';

use Ocs\Migrate\CoreDb;

/**
 * @param string[] $argv
 * @return array{positional:string[], flags:array<string, string|bool>}
 */
function ocs_parse_args(array $argv): array
{
    $positional = [];
    $flags = [];
    $count = count($argv);
    for ($i = 1; $i < $count; $i++) {
        $a = $argv[$i];
        if (strncmp($a, '--', 2) === 0) {
            $name = substr($a, 2);
            if (strpos($name, '=') !== false) {
                [$name, $val] = explode('=', $name, 2);
                $flags[$name] = $val;
            } elseif (isset($argv[$i + 1]) && strncmp($argv[$i + 1], '--', 2) !== 0 && in_array($name, ['config', 'to', 'accept-checksum', 'username', 'email', 'password', 'env-file'], true)) {
                $flags[$name] = $argv[++$i];
            } else {
                $flags[$name] = true;
            }
        } else {
            $positional[] = $a;
        }
    }
    return ['positional' => $positional, 'flags' => $flags];
}

/** @return array<string, string> */
function ocs_parse_env_file(string $path): array
{
    $out = [];
    if (!is_file($path)) {
        return $out;
    }
    foreach (preg_split('/\R/', (string) file_get_contents($path)) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $v = trim($v);
        if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) {
            $v = substr($v, 1, -1);
        }
        $out[trim($k)] = $v;
    }
    return $out;
}

/** Loads the build's generated config.php (defines DB_* constants) and connects with core's DB class. */
function ocs_connect(string $repoRoot, string $configPath): CoreDb
{
    if (!extension_loaded('mysqli')) {
        fwrite(STDERR, "FAIL mysqli extension is not loaded. Run PHP with: php -d extension=mysqli <script>\n");
        exit(1);
    }
    if (!is_file($configPath)) {
        fwrite(STDERR, "FAIL config not found: {$configPath} (run: npm run build)\n");
        exit(1);
    }
    require_once $configPath;
    foreach (['DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PORT', 'DB_PREFIX'] as $c) {
        if (!defined($c)) {
            fwrite(STDERR, "FAIL {$c} not defined in {$configPath}\n");
            exit(1);
        }
    }
    try {
        return new CoreDb($repoRoot . '/core/upload/system/library', DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (string) DB_PORT);
    } catch (\Throwable $e) {
        fwrite(STDERR, 'FAIL database connection: ' . preg_replace('/<br \/>.*/s', '', $e->getMessage()) . "\n");
        exit(1);
    }
}
