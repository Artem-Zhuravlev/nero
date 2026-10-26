<?php
declare(strict_types=1);

// SPEC-039 REQ-VIS-010: php -d extension=mysqli scripts/seed-demo.php [--config path]
// Switches on and fills the storefront blocks of a LOCAL stand so the pages can be judged with data (logic: Ocs\Setup\DemoSeed,
// shared with the installable package, SPEC-040). Idempotent; refuses to run unless APP_ENV=local. Never run it on a live shop.

require_once __DIR__ . '/lib/php/cli.php';

use Ocs\Migrate\Context;
use Ocs\Setup\DemoSeed;

$root = dirname(__DIR__);
$args = ocs_parse_args($argv);
$env = ocs_parse_env_file($root . '/.env');

if (($env['APP_ENV'] ?? 'local') !== 'local') {
    fwrite(STDERR, "FAIL seed-demo.php only runs with APP_ENV=local\n");
    exit(1);
}

$config = is_string($args['flags']['config'] ?? null) ? (string) $args['flags']['config'] : $root . '/build/www/config.php';
$db = ocs_connect($root, $config);
$m = new Context($db, DB_PREFIX, $root);

(new DemoSeed($m, static function (string $line): void {
    echo $line . "\n";
}))->run();

echo "done: run `php -d extension=mysqli scripts/refresh.php` and clear var/storage/cache if blocks look stale.\n";
