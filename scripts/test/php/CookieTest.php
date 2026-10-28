<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\Migrate\Context;
use Ocs\Test\Support\SqliteDb;

require_once dirname(__DIR__, 3) . '/custom/extensions/ocs_shell/upload/system/library/ocs/shell.php';
require_once dirname(__DIR__, 3) . '/custom/extensions/ocs_cookie/upload/system/library/ocs/cookie.php';

final class CookieTest extends TestCase
{
    private function settings(array $l, array $extra = []): array
    {
        return $extra + ['l' => ['uk-ua' => $l], 'position' => 'card', 'version' => 1, 'days' => 365];
    }

    // TEST-CKP-002 - verifies REQ-CKP-003, REQ-CKP-011 (escaping, unsafe terms URL dropped, no text = no popup)
    public function test_texts_urls_and_language(): void
    {
        $this->assertSame(null, \Ocs\Cookie::build($this->settings(['text' => '  ']), 'uk-ua'));
        $this->assertSame(null, \Ocs\Cookie::build($this->settings(['text' => 'Hi']), 'en-gb'));

        $c = \Ocs\Cookie::build($this->settings(['text' => 'We <b>use</b> cookies', 'link_label' => 'Terms', 'link_url' => '/cookie-terms', 'accept' => 'Yes', 'necessary' => 'No']), 'uk-ua');
        $this->assertSame('We &lt;b&gt;use&lt;/b&gt; cookies', $c['text']);
        $this->assertSame('/cookie-terms', $c['link_href']);
        $this->assertSame('Yes', $c['accept']);
        $this->assertSame('No', $c['necessary']);

        $bad = \Ocs\Cookie::build($this->settings(['text' => 'A', 'link_label' => 'T', 'link_url' => 'javascript:alert(1)']), 'uk-ua');
        $this->assertSame('', $bad['link_href']);
        $this->assertSame('OK', $bad['accept']);
        $this->assertSame('', \Ocs\Cookie::build($this->settings(['text' => 'A', 'link_url' => '/x']), 'uk-ua')['link_href']);
    }

    // TEST-CKP-002 - verifies REQ-CKP-002 (position, version and days are clamped to valid values)
    public function test_position_version_days(): void
    {
        $t = ['text' => 'Hi'];
        $this->assertSame('card', \Ocs\Cookie::build($this->settings($t, ['position' => 'nonsense']), 'uk-ua')['position']);
        $this->assertSame('bar', \Ocs\Cookie::build($this->settings($t, ['position' => 'bar']), 'uk-ua')['position']);
        $this->assertSame(1, \Ocs\Cookie::build($this->settings($t, ['version' => 0]), 'uk-ua')['version']);
        $this->assertSame(7, \Ocs\Cookie::build($this->settings($t, ['version' => '7']), 'uk-ua')['version']);
        $this->assertSame(730, \Ocs\Cookie::build($this->settings($t, ['days' => 99999]), 'uk-ua')['days']);
        $this->assertSame(1, \Ocs\Cookie::build($this->settings($t, ['days' => 0]), 'uk-ua')['days']);
    }

    // TEST-CKP-001 - verifies REQ-CKP-001, REQ-CKP-010 (install migration: disabled by default, event registered, idempotent)
    public function test_install_migration_defaults_to_disabled_and_registers_event(): void
    {
        $db = new SqliteDb();
        $db->createCoreSubset();
        $m = new Context($db, 'oc_', dirname(__DIR__, 3));
        $run = function () use ($m): void {
            $fn = require dirname(__DIR__, 3) . '/custom/extensions/ocs_cookie/db/001_install.php';
            $fn($m);
        };
        $run();
        $run();
        $this->assertSame('0', (string) $m->setting->get('module_ocs_cookie', 'module_ocs_cookie_status'));
        $this->assertTrue($m->extension->isRegistered('module', 'ocs_cookie'));
        $this->assertCount(1, $db->pdo->query("SELECT 1 FROM oc_event WHERE code = 'ocs_cookie_footer_before'")->fetchAll());
    }
}
