<?php
declare(strict_types=1);

// Test driver for Ocs\Refresh\Refresher (run in a subprocess because core defines global constants).
// usage: php refresh_driver.php <fixture.json>
// Runs the REAL core OCMOD algorithm against the REAL core files (read-only) with a fake database;
// all output goes to the temp dir given in the fixture.

$fx = json_decode((string) file_get_contents($argv[1]), true);
$core = dirname(__DIR__, 4) . '/core/upload/';
$tmp = rtrim(str_replace('\\', '/', $fx['tmp']), '/') . '/';

foreach (['storage/cache', 'storage/logs', 'storage/modification'] as $d) {
    if (!is_dir($tmp . $d)) {
        mkdir($tmp . $d, 0777, true);
    }
}
file_put_contents($tmp . 'storage/modification/index.html', '');
file_put_contents($tmp . 'storage/cache/index.html', '');
file_put_contents($tmp . 'storage/cache/stale.cache', 'x');

define('HTTP_SERVER', 'http://localhost/admin/');
define('HTTP_CATALOG', 'http://localhost/');
define('HTTPS_SERVER', 'http://localhost/admin/');
define('HTTPS_CATALOG', 'http://localhost/');
define('DIR_APPLICATION', $core . 'admin/');
define('DIR_SYSTEM', $core . 'system/');
define('DIR_IMAGE', $core . 'image/');
define('DIR_STORAGE', $tmp . 'storage/');
define('DIR_CATALOG', $core . 'catalog/');
define('DIR_LANGUAGE', DIR_APPLICATION . 'language/');
define('DIR_TEMPLATE', DIR_APPLICATION . 'view/template/');
define('DIR_CONFIG', DIR_SYSTEM . 'config/');
define('DIR_CACHE', DIR_STORAGE . 'cache/');
define('DIR_DOWNLOAD', DIR_STORAGE . 'download/');
define('DIR_LOGS', DIR_STORAGE . 'logs/');
define('DIR_MODIFICATION', DIR_STORAGE . 'modification/');
define('DIR_SESSION', DIR_STORAGE . 'session/');
define('DIR_UPLOAD', DIR_STORAGE . 'upload/');
define('DB_DRIVER', 'mysqli');
define('DB_HOSTNAME', 'x');
define('DB_USERNAME', 'x');
define('DB_PASSWORD', 'x');
define('DB_DATABASE', 'x');
define('DB_PORT', '3306');
define('DB_PREFIX', 'oc_');

require_once DIR_SYSTEM . 'startup.php';
// Core targets PHP 7.x: silence its PHP 8 deprecations (SPEC-001 REQ-ARCH-003), keep real errors.
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 3) . '/lib/php/autoload.php';

/** Answers the few queries the core refresh makes. */
final class FakeCoreDb
{
    public $writes = [];
    private $fx;

    public function __construct(array $fx)
    {
        $this->fx = $fx;
    }

    public function query($sql)
    {
        $r = new stdClass();
        $r->rows = [];
        if (stripos($sql, 'FROM `oc_modification`') !== false) {
            $r->rows = $this->fx['modifications'];
        } elseif (stripos($sql, 'FROM `oc_setting`') !== false) {
            $r->rows = $this->fx['settings'];
        } elseif (stripos($sql, 'FROM `oc_theme`') !== false) {
            $r->rows = $this->fx['themes'] ?? [];
        } elseif (stripos($sql, 'UPDATE') === 0) {
            $this->writes[] = $sql;
        }
        $r->num_rows = count($r->rows);
        $r->row = $r->rows[0] ?? [];
        return $r;
    }

    public function escape($v)
    {
        return addslashes((string) $v);
    }

    public function countAffected()
    {
        return 0;
    }

    public function getLastId()
    {
        return 0;
    }

    public function connected()
    {
        return true;
    }
}

$db = new FakeCoreDb($fx);
$result = (new Ocs\Refresh\Refresher($db))->refresh();
$result['cacheRemoved'] = Ocs\Refresh\Refresher::clearCache(DIR_CACHE);
$result['writes'] = $db->writes;
$result['modified'] = [];
foreach ($result['files'] as $f) {
    $result['modified'][$f] = file_get_contents(DIR_MODIFICATION . $f);
}
unset($result['log']);
echo json_encode($result);
