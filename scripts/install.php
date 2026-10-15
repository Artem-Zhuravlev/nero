<?php
declare(strict_types=1);

// SPEC-001 REQ-ARCH-032: php scripts/install.php [--config <generated config.php>] [--username u] [--email e]
//   [--password p | env ADMIN_PASS] [--force]
// Replays the stock installer's DB steps (never runs the stock installer against core/).

require_once __DIR__ . '/lib/php/cli.php';

use Ocs\Install\Installer;

$root = dirname(__DIR__);
$args = ocs_parse_args($argv);
$flags = $args['flags'];
$config = is_string($flags['config'] ?? null) ? (string) $flags['config'] : $root . '/build/www/config.php';
$env = ocs_parse_env_file($root . '/.env');

$username = is_string($flags['username'] ?? null) ? $flags['username'] : ($env['ADMIN_USER'] ?? 'admin');
$email = is_string($flags['email'] ?? null) ? $flags['email'] : ($env['ADMIN_EMAIL'] ?? '');
$password = is_string($flags['password'] ?? null) ? $flags['password'] : (string) ($env['ADMIN_PASS'] ?? '');
$generated = false;
if ($password === '') {
    $password = Installer::token(20);
    $generated = true;
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "FAIL a valid admin e-mail is required (--email or ADMIN_EMAIL in .env)\n");
    exit(1);
}
if (!preg_match('/^[A-Za-z0-9_.-]{1,20}$/', $username)) {
    fwrite(STDERR, "FAIL admin username must be 1-20 chars [A-Za-z0-9_.-] (oc_user.username is varchar(20))\n");
    exit(1);
}

$db = ocs_connect($root, $config);
try {
    (new Installer($db))->install([
        'prefix' => DB_PREFIX,
        'sqlFile' => $root . '/core/upload/install/opencart.sql',
        'username' => $username,
        'email' => $email,
        'password' => $password,
        'force' => !empty($flags['force']),
    ]);
} catch (\Throwable $e) {
    fwrite(STDERR, 'FAIL ' . $e->getMessage() . "\n");
    exit(1);
}
if ($generated) {
    echo "generated admin password (shown once): {$password}\n";
}
exit(0);
