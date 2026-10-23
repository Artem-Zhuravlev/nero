<?php
declare(strict_types=1);

// SPEC-005 REQ-DB-040/041: headless "Refresh modifications" (core algorithm) + cache clear.
// usage: php -d extension=mysqli scripts/refresh.php [--config <admin config.php>] [--no-cache-clear]
// Exit: 0 ok, 1 failure (an OCMOD with error="abort" did not match, or bootstrap error).

require_once __DIR__ . '/lib/php/cli.php';

use Ocs\Refresh\Refresher;

$root = dirname(__DIR__);
$args = ocs_parse_args($argv);
$flags = $args['flags'];
$env = ocs_parse_env_file($root . '/.env');
$adminDir = $env['ADMIN_DIR'] ?? 'admin';
$config = is_string($flags['config'] ?? null) ? (string) $flags['config'] : $root . '/build/www/' . $adminDir . '/config.php';

if (!extension_loaded('mysqli')) {
    fwrite(STDERR, "FAIL mysqli extension is not loaded. Run PHP with: php -d extension=mysqli scripts/refresh.php\n");
    exit(1);
}
if (!is_file($config)) {
    fwrite(STDERR, "FAIL admin config not found: {$config} (run: npm run build)\n");
    exit(1);
}

// The admin config defines DIR_APPLICATION/DIR_CATALOG/DIR_SYSTEM/DIR_MODIFICATION/... - the same world core's admin runs in.
require_once $config;
require_once DIR_SYSTEM . 'startup.php';
// Core targets PHP 7.x: silence its PHP 8 deprecations (SPEC-001 REQ-ARCH-003), keep real errors.
error_reporting(E_ALL & ~E_DEPRECATED);

try {
    $db = new DB('mysqli', DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (string) DB_PORT);
    $result = (new Refresher($db))->refresh();
} catch (\Throwable $e) {
    fwrite(STDERR, 'FAIL ' . preg_replace('/<br \/>.*/s', '', $e->getMessage()) . "\n");
    exit(1);
}

foreach ($result['warnings'] as $w) {
    echo "warning: {$w}\n";
}
foreach ($result['failures'] as $f) {
    echo "FAIL {$f}\n";
}
echo 'modified files: ' . count($result['files']) . "\n";
if (empty($flags['no-cache-clear'])) {
    echo 'cache files removed: ' . Refresher::clearCache(DIR_CACHE) . "\n";
}
exit($result['ok'] ? 0 : 1);
