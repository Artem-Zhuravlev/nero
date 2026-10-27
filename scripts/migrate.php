<?php
declare(strict_types=1);

// SPEC-005 REQ-DB-001: php scripts/migrate.php [up|status|down <ext-code>] [--dry-run] [--to <id>]
//   [--config <path to generated config.php>] [--no-backup-check] [--accept-checksum <id>]
// Exit codes: 0 ok, 1 failure, 3 lint/checksum/discovery error, 4 another runner holds the lock.

require_once __DIR__ . '/lib/php/cli.php';

use Ocs\Migrate\Runner;

$root = dirname(__DIR__);
$args = ocs_parse_args($argv);
$cmd = $args['positional'][0] ?? 'up';
$flags = $args['flags'];
$config = is_string($flags['config'] ?? null) ? (string) $flags['config'] : $root . '/build/www/config.php';

if (!in_array($cmd, ['up', 'status', 'down'], true)) {
    fwrite(STDERR, "usage: php scripts/migrate.php [up|status|down <ext-code>] [--dry-run] [--to id] [--config path] [--no-backup-check] [--accept-checksum id]\n");
    exit(1);
}

$env = ocs_parse_env_file($root . '/.env');
$isLocal = ($env['APP_ENV'] ?? 'local') === 'local';

$db = ocs_connect($root, $config);
$runner = new Runner($db, DB_PREFIX, $root);
$runner->ocmodDir = dirname($config) . '/system';

if ($cmd === 'status') {
    foreach ($runner->status() as $row) {
        echo str_pad($row['state'], 8) . ' ' . $row['id'] . "\n";
    }
    exit(0);
}
if ($cmd === 'down') {
    $code = $args['positional'][1] ?? '';
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $code)) {
        fwrite(STDERR, "usage: php scripts/migrate.php down <ext-code>\n");
        exit(1);
    }
    exit($runner->down($code));
}

$accept = isset($flags['accept-checksum']) && is_string($flags['accept-checksum']) ? [$flags['accept-checksum']] : [];
if ($accept && !$isLocal) {
    fwrite(STDERR, "FAIL --accept-checksum is allowed only with APP_ENV=local\n");
    exit(3);
}
$code = $runner->up([
    'dryRun' => !empty($flags['dry-run']),
    'to' => is_string($flags['to'] ?? null) ? $flags['to'] : null,
    'requireBackup' => !$isLocal && empty($flags['no-backup-check']),
    'acceptChecksum' => $accept,
]);
exit($code);
