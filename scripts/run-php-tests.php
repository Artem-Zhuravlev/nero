<?php
declare(strict_types=1);

// SPEC-021 REQ-TST-010: dependency-free PHP test runner.
// usage: php scripts/run-php-tests.php [--filter text] [--junit out.xml]
// Discovers *Test.php under scripts/test/php/ and custom/**/tests/, runs test_* methods of classes extending Ocs\Test\TestCase.

require_once __DIR__ . '/lib/php/autoload.php';
require_once __DIR__ . '/test/php/AssertionFailed.php';
require_once __DIR__ . '/test/php/TestCase.php';

$root = dirname(__DIR__);
$filter = null;
$junit = null;
for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--filter' && isset($argv[$i + 1])) {
        $filter = $argv[++$i];
    } elseif ($argv[$i] === '--junit' && isset($argv[$i + 1])) {
        $junit = $argv[++$i];
    }
}

error_reporting(E_ALL);
set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
    throw new ErrorException($str, 0, $no, $file, $line);
});

/** @return string[] */
function ocs_find_tests(string $dir): array
{
    $out = [];
    if (!is_dir($dir)) {
        return $out;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && substr($f->getFilename(), -8) === 'Test.php') {
            $out[] = $f->getPathname();
        }
    }
    sort($out);
    return $out;
}

$files = ocs_find_tests($root . '/scripts/test/php');
foreach (glob($root . '/custom/extensions/*/tests', GLOB_ONLYDIR) ?: [] as $d) {
    $files = array_merge($files, ocs_find_tests($d));
}
$files = array_merge($files, ocs_find_tests($root . '/custom/tests'));

// Support classes used by tests.
foreach (glob($root . '/scripts/test/php/Support/*.php') ?: [] as $support) {
    require_once $support;
}

$before = get_declared_classes();
foreach ($files as $file) {
    require_once $file;
}
$classes = array_values(array_filter(array_diff(get_declared_classes(), $before), static function (string $c): bool {
    return is_subclass_of($c, \Ocs\Test\TestCase::class) && !(new ReflectionClass($c))->isAbstract();
}));

$pass = 0;
$fail = 0;
$cases = [];
$t0 = microtime(true);
foreach ($classes as $class) {
    foreach (get_class_methods($class) as $method) {
        if (strncmp($method, 'test_', 5) !== 0) {
            continue;
        }
        $name = $class . '::' . $method;
        if ($filter !== null && stripos($name, $filter) === false) {
            continue;
        }
        $obj = new $class();
        $start = microtime(true);
        $err = null;
        try {
            $obj->runSetUp();
            $obj->$method();
        } catch (\Throwable $e) {
            $err = $e;
        } finally {
            try {
                $obj->runTearDown();
            } catch (\Throwable $e2) {
                $err = $err ?? $e2;
            }
        }
        $ms = (int) round((microtime(true) - $start) * 1000);
        if ($err === null) {
            $pass++;
            echo "ok   {$name} ({$ms}ms)\n";
        } else {
            $fail++;
            echo "FAIL {$name}\n     " . get_class($err) . ': ' . $err->getMessage() . "\n     at " . basename($err->getFile()) . ':' . $err->getLine() . "\n";
        }
        $cases[] = [$name, $ms, $err];
    }
}

printf("\n%d passed, %d failed, %d total (%.2fs)\n", $pass, $fail, $pass + $fail, microtime(true) - $t0);

if ($junit !== null) {
    $xml = new SimpleXMLElement('<testsuite/>');
    $xml['name'] = 'ocs-php';
    $xml['tests'] = (string) count($cases);
    $xml['failures'] = (string) $fail;
    foreach ($cases as [$name, $ms, $err]) {
        $tc = $xml->addChild('testcase');
        $tc['name'] = $name;
        $tc['time'] = (string) ($ms / 1000);
        if ($err !== null) {
            $tc->addChild('failure', htmlspecialchars($err->getMessage(), ENT_XML1));
        }
    }
    file_put_contents($junit, $xml->asXML());
}
exit($fail > 0 || $pass === 0 ? 1 : 0);
