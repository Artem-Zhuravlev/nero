<?php
// Extension: ocs_novaposhta. CLI entry for cron: loads one Nova Poshta directory (or all) into the local tables. Owner: SPEC-085.
//
//   php system/library/ocs/novaposhta/bin/sync.php areas|cities|warehouses|all [--dry-run] [--reset]
//
// Cron example (each directory at its own time, REQ-NP-001):
//   10 3 * * *  php /path/to/docroot/system/library/ocs/novaposhta/bin/sync.php areas
//   20 3 * * *  php /path/to/docroot/system/library/ocs/novaposhta/bin/sync.php cities
//   40 3 * * *  php /path/to/docroot/system/library/ocs/novaposhta/bin/sync.php warehouses
// Exit code: 0 ok, 1 error, 2 another run holds the lock, 64 usage.

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$entities = array('areas', 'cities', 'warehouses');
$args = array_slice($argv, 1);
$dry = in_array('--dry-run', $args, true);
$reset = in_array('--reset', $args, true);
$names = array_values(array_filter($args, function ($a) {
	return $a !== '' && $a[0] !== '-';
}));

if (!$names || (count($names) > 1) || ($names[0] !== 'all' && !in_array($names[0], $entities, true))) {
	fwrite(STDERR, "usage: php sync.php areas|cities|warehouses|all [--dry-run] [--reset]\n");
	exit(64);
}

$docroot = dirname(__DIR__, 5);
$config = is_file($docroot . '/config.php') ? $docroot . '/config.php' : null;

if (!$config) {
	fwrite(STDERR, "config.php not found in {$docroot}\n");
	exit(1);
}

require_once $config;
require_once DIR_SYSTEM . 'library/db.php';
require_once DIR_SYSTEM . 'library/db/' . DB_DRIVER . '.php';

spl_autoload_register(function ($class) {
	if (strncmp($class, 'Ocs\\Novaposhta\\', 15) === 0) {
		$file = dirname(__DIR__) . '/' . strtolower(substr($class, 15)) . '.php';

		if (is_file($file)) {
			require_once $file;
		}
	}
});

$oc = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
$db = new Ocs\Novaposhta\OcDb($oc);

$settings = array();

foreach ($db->rows("SELECT `key`, `value` FROM `" . DB_PREFIX . "setting` WHERE store_id = 0 AND `code` = 'shipping_ocs_novaposhta'") as $row) {
	$settings[$row['key']] = $row['value'];
}

if (empty($settings['shipping_ocs_novaposhta_status'])) {
	fwrite(STDERR, "Nova Poshta shipping is disabled\n");
	exit(1);
}

$client = new Ocs\Novaposhta\Client(isset($settings['shipping_ocs_novaposhta_api_key']) ? $settings['shipping_ocs_novaposhta_api_key'] : '');
$sync = new Ocs\Novaposhta\Sync($db, DB_PREFIX, $client, array(
	'limit'    => isset($settings['shipping_ocs_novaposhta_batch_size']) ? (int)$settings['shipping_ocs_novaposhta_batch_size'] : 500,
	'pause_ms' => isset($settings['shipping_ocs_novaposhta_pause_ms']) ? (int)$settings['shipping_ocs_novaposhta_pause_ms'] : 200
));

$code = 0;

foreach ($names[0] === 'all' ? $entities : array($names[0]) as $entity) {
	if ($reset) {
		$sync->reset($entity);
	}

	$result = $sync->run($entity, 0, $dry);

	echo date('c') . ' ' . $entity . ' ' . $result['status'] . ' rows=' . $result['rows'] . ($result['error'] !== '' ? ' error=' . $result['error'] : '') . ($dry ? ' (dry-run)' : '') . "\n";

	if ($result['status'] === 'error') {
		$code = 1;
	} elseif ($result['status'] === 'locked' && !$code) {
		$code = 2;
	}
}

exit($code);
