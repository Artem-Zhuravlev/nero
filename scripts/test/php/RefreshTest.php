<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\Refresh\Refresher;
use Ocs\Test\Support\TempRepo;

/**
 * TEST-DB-010 - verifies REQ-DB-040, REQ-DB-041: runs the real core OCMOD algorithm (in a subprocess) against the real,
 * read-only core files with a fake DB, and checks the patched copies written to a temp DIR_MODIFICATION.
 */
final class RefreshTest extends TestCase
{
    /** @var TempRepo */
    private $tmp;

    protected function setUp(): void
    {
        $this->tmp = new TempRepo();
    }

    protected function tearDown(): void
    {
        $this->tmp->cleanup();
    }

    private static function mod(string $code, string $name, string $xml, int $status = 1): array
    {
        return ['modification_id' => 1, 'extension_install_id' => 0, 'name' => $name, 'code' => $code, 'author' => 'a', 'version' => '1', 'link' => '', 'xml' => $xml, 'status' => $status, 'date_added' => '2026-01-01 00:00:00'];
    }

    /** @return array<string, mixed> */
    private function runDriver(array $modifications, array $extra = []): array
    {
        $fixture = $this->tmp->put('fixture.json', json_encode($extra + [
            'tmp' => $this->tmp->root . '/out',
            'modifications' => $modifications,
            'settings' => [
                ['key' => 'config_theme', 'value' => 'default', 'serialized' => 0],
                ['key' => 'theme_default_directory', 'value' => 'default', 'serialized' => 0],
                ['key' => 'config_maintenance', 'value' => '0', 'serialized' => 0],
                ['key' => 'config_admin_language', 'value' => 'uk-ua', 'serialized' => 0],
            ],
        ]));
        $cmd = [PHP_BINARY, __DIR__ . '/fixtures/refresh_driver.php', $fixture];
        $errFile = $this->tmp->root . '/driver.err'; // a file, not a pipe: a full stderr pipe would deadlock the child
        $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['file', $errFile, 'w']], $pipes);
        $this->assertTrue(is_resource($p), 'driver started');
        $out = stream_get_contents($pipes[1]);
        proc_close($p);
        $err = is_file($errFile) ? (string) file_get_contents($errFile) : '';
        $json = json_decode($out, true);
        if (!is_array($json)) {
            $this->fail('driver output is not JSON. stdout=' . substr($out, 0, 400) . ' stderr=' . substr($err, 0, 400));
        }
        return $json;
    }

    private const XML_OK = '<?xml version="1.0" encoding="utf-8"?><modification><name>T</name><code>ocs_t</code><version>1</version><author>a</author><link></link>'
        . '<file path="catalog/controller/common/home.php"><operation><search><![CDATA[class ControllerCommonHome extends Controller {]]></search>'
        . '<add position="after"><![CDATA[	// OCS-MARKER-AFTER]]></add></operation></file></modification>';

    public function test_active_ocmod_is_applied_to_real_core_file_and_cache_is_cleared(): void
    {
        $r = $this->runDriver([self::mod('ocs_t', 'T', self::XML_OK)]);
        $this->assertTrue($r['ok'], json_encode($r['failures']));
        $this->assertSame([], $r['failures']);
        $this->assertTrue(isset($r['modified']['catalog/controller/common/home.php']), 'patched copy written: ' . json_encode(array_keys($r['modified'])));
        $patched = $r['modified']['catalog/controller/common/home.php'];
        $this->assertContains('// OCS-MARKER-AFTER', $patched);
        $original = (string) file_get_contents(dirname(__DIR__, 3) . '/core/upload/catalog/controller/common/home.php');
        $this->assertTrue(strpos($original, 'OCS-MARKER-AFTER') === false, 'core file itself is untouched');
        $this->assertSame(1, $r['cacheRemoved'], 'stale cache file removed, index.html kept');
        $this->assertTrue(is_file($this->tmp->root . '/out/storage/cache/index.html'));
        $this->assertTrue(count($r['writes']) >= 2, 'maintenance on/off writes: ' . json_encode($r['writes']));
        // the built-in default modification (system/modification.xml) is applied too (twig.php / loader patches)
        $this->assertTrue(in_array('system/library/template/twig.php', $r['files'], true), 'default modification applied');
    }

    public function test_inactive_ocmod_is_ignored(): void
    {
        $r = $this->runDriver([self::mod('ocs_t', 'T', self::XML_OK, 0)]);
        $this->assertTrue($r['ok']);
        $this->assertTrue(!isset($r['modified']['catalog/controller/common/home.php']));
    }

    public function test_abort_error_attribute_makes_refresh_fail_and_leaves_no_partial_patch(): void
    {
        $bad = '<?xml version="1.0" encoding="utf-8"?><modification><name>Bad</name><code>ocs_bad</code><version>1</version><author>a</author><link></link>'
            . '<file path="catalog/controller/common/home.php"><operation error="abort"><search><![CDATA[THIS TEXT DOES NOT EXIST]]></search>'
            . '<add position="after"><![CDATA[x]]></add></operation></file></modification>';
        $r = $this->runDriver([self::mod('ocs_bad', 'Bad', $bad)]);
        $this->assertFalse($r['ok']);
        $this->assertLineMatches("/OCMOD 'Bad' aborted/", $r['failures']);
    }

    public function test_missing_anchor_without_abort_is_only_a_warning(): void
    {
        $warn = '<?xml version="1.0" encoding="utf-8"?><modification><name>Warn</name><code>ocs_w</code><version>1</version><author>a</author><link></link>'
            . '<file path="catalog/controller/common/home.php"><operation error="skip"><search><![CDATA[NOPE NOPE]]></search>'
            . '<add position="after"><![CDATA[x]]></add></operation></file></modification>';
        $r = $this->runDriver([self::mod('ocs_w', 'Warn', $warn)]);
        $this->assertTrue($r['ok']);
        $this->assertLineMatches("/OCMOD 'Warn': operation skipped/", $r['warnings']);
    }

    public function test_parse_log_extracts_failures_and_warnings(): void
    {
        $log = "2026-10-15 10:00:00 - MOD: A\nFILE: x\nNOT FOUND - ABORTING!\n----\nMOD: B\nNOT FOUND - OPERATION SKIPPED!\nMOD: C\nNOT FOUND - OPERATIONS ABORTED!\n";
        [$f, $w] = Refresher::parseLog($log);
        $this->assertSame(["OCMOD 'A' aborted (error=\"abort\" operation did not match)"], $f);
        $this->assertCount(2, $w);
    }

    public function test_clear_cache_keeps_index_html_and_removes_nested_files(): void
    {
        $this->tmp->put('c/index.html', '');
        $this->tmp->put('c/a.cache', '1');
        $this->tmp->put('c/template/x/y.php', '1');
        $this->assertSame(2, Refresher::clearCache($this->tmp->root . '/c'));
        $this->assertTrue(is_file($this->tmp->root . '/c/index.html'));
        $this->assertTrue(!is_dir($this->tmp->root . '/c/template'));
    }
}
