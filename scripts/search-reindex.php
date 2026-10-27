<?php
declare(strict_types=1);

// SPEC-079 REQ-SSR-022: full rebuild of the super search index from the command line (cron / after imports).
// usage: php -d extension=mysqli scripts/search-reindex.php [--config <admin config.php>]
// Exit: 0 ok, 1 failure.

require_once __DIR__ . '/lib/php/cli.php';

$root = dirname(__DIR__);
$args = ocs_parse_args($argv);
$flags = $args['flags'];
$env = ocs_parse_env_file($root . '/.env');
$adminDir = $env['ADMIN_DIR'] ?? 'admin';
$config = is_string($flags['config'] ?? null) ? (string) $flags['config'] : $root . '/build/www/' . $adminDir . '/config.php';

if (!extension_loaded('mysqli')) {
    fwrite(STDERR, "FAIL mysqli extension is not loaded. Run PHP with: php -d extension=mysqli scripts/search-reindex.php\n");
    exit(1);
}
if (!is_file($config)) {
    fwrite(STDERR, "FAIL admin config not found: {$config} (run: npm run build)\n");
    exit(1);
}

require_once $config;
require_once DIR_SYSTEM . 'startup.php';
error_reporting(E_ALL & ~E_DEPRECATED);

try {
    $db = new DB('mysqli', DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (string) DB_PORT);
    $index = new \Ocs\SuperSearchIndex($db, DB_PREFIX, null);

    $index->clear();
    $index->setSetting('module_ocs_super_search_built_at', '');

    $after = 0;
    $done = 0;
    while (($ids = $index->nextIds($after, 200)) !== []) {
        $index->reindex($ids);
        $after = max($ids);
        $done += count($ids);
        echo "\rindexed {$done}";
    }

    $index->setSetting('module_ocs_super_search_built_at', date('Y-m-d H:i:s'));
    $index->touch();
} catch (\Throwable $e) {
    fwrite(STDERR, 'FAIL ' . preg_replace('/<br \/>.*/s', '', $e->getMessage()) . "\n");
    exit(1);
}

echo "\nproducts: {$done}, tokens: " . $index->countTokens() . "\n";
exit(0);
