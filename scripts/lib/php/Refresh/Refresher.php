<?php
declare(strict_types=1);

namespace Ocs\Refresh;

/**
 * Headless OCMOD refresh + cache clear (SPEC-005 REQ-DB-040, REQ-DB-041).
 *
 * Preconditions (done by the caller): the admin config constants are defined (DIR_APPLICATION, DIR_CATALOG, DIR_SYSTEM,
 * DIR_MODIFICATION, DIR_LOGS, DIR_CACHE, DIR_CONFIG, DIR_LANGUAGE, DB_PREFIX ...) and core system/startup.php is loaded.
 */
final class Refresher
{
    /** @var object core \DB or any object with query()/escape()/countAffected()/getLastId() */
    private $db;

    /** @param object $db */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * @return array{ok:bool, failures:string[], warnings:string[], files:string[], log:string}
     */
    public function refresh(): array
    {
        require_once __DIR__ . '/HeadlessModification.php';

        $registry = new \Registry();
        $config = new \Config();
        $config->load('default');
        $config->load('admin');
        foreach ($this->db->query('SELECT * FROM `' . DB_PREFIX . 'setting` WHERE store_id = 0')->rows as $row) {
            $config->set($row['key'], (int) $row['serialized'] ? json_decode($row['value'], true) : $row['value']);
        }
        $config->set('config_store_id', 0);
        $registry->set('config', $config);
        $registry->set('db', $this->db);
        $registry->set('event', new \Event($registry));
        $registry->set('load', new \Loader($registry));
        $registry->set('request', new \Request());
        $registry->set('language', new \Language((string) ($config->get('config_admin_language') ?: 'uk-ua')));
        $registry->set('document', new \Document());
        $session = new \stdClass();
        $session->data = [];
        $registry->set('session', $session);

        $controller = new \OcsHeadlessModification($registry);
        $controller->refresh();

        $log = is_file(DIR_LOGS . 'ocmod.log') ? (string) file_get_contents(DIR_LOGS . 'ocmod.log') : '';
        [$failures, $warnings] = self::parseLog($log);

        return ['ok' => !$failures, 'failures' => $failures, 'warnings' => $warnings, 'files' => self::listFiles(DIR_MODIFICATION), 'log' => $log];
    }

    /**
     * ocmod.log lines: "MOD: <name>", "NOT FOUND - ABORTING!" (error="abort" -> failure),
     * "NOT FOUND - OPERATIONS ABORTED!" / "OPERATION SKIPPED!" (warnings).
     *
     * @return array{0:string[], 1:string[]} failures, warnings
     */
    public static function parseLog(string $log): array
    {
        $failures = [];
        $warnings = [];
        $mod = '(unknown)';
        foreach (preg_split('/\R/', $log) ?: [] as $line) {
            $line = preg_replace('/^\d{4}-\d{2}-\d{2} \d{1,2}:\d{2}:\d{2} - /', '', $line);
            if (strncmp($line, 'MOD: ', 5) === 0) {
                $mod = trim(substr($line, 5));
            } elseif (strpos($line, 'NOT FOUND - ABORTING!') !== false) {
                $failures[] = "OCMOD '{$mod}' aborted (error=\"abort\" operation did not match)";
            } elseif (strpos($line, 'NOT FOUND - OPERATIONS ABORTED!') !== false) {
                $warnings[] = "OCMOD '{$mod}': operation did not match, remaining operations of that file skipped";
            } elseif (strpos($line, 'NOT FOUND - OPERATION SKIPPED!') !== false) {
                $warnings[] = "OCMOD '{$mod}': operation skipped (not found)";
            }
        }
        return [$failures, $warnings];
    }

    /** @return string[] relative file paths below $dir (excluding index.html) */
    public static function listFiles(string $dir): array
    {
        $out = [];
        if (!is_dir($dir)) {
            return $out;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getFilename() !== 'index.html') {
                $out[] = str_replace('\\', '/', substr($f->getPathname(), strlen(rtrim(str_replace('\\', '/', $dir), '/')) + 1));
            }
        }
        sort($out);
        return $out;
    }

    /**
     * Removes cache files (incl. compiled Twig) but keeps index.html guards. Returns number of removed files.
     */
    public static function clearCache(string $cacheDir): int
    {
        $n = 0;
        if (!is_dir($cacheDir)) {
            return 0;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($cacheDir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            if ($f->isFile()) {
                if ($f->getFilename() !== 'index.html' && @unlink($f->getPathname())) {
                    $n++;
                }
            } elseif ($f->isDir()) {
                @rmdir($f->getPathname());
            }
        }
        return $n;
    }
}
