<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\Migrate\Context;
use Ocs\Test\Support\SqliteDb;

require_once dirname(__DIR__, 3) . '/custom/extensions/ocs_shell/upload/system/library/ocs/shell.php';
require_once dirname(__DIR__, 3) . '/custom/extensions/ocs_topbar/upload/system/library/ocs/topbar.php';

final class TopbarTest extends TestCase
{
    private function settings(array $l, array $extra = []): array
    {
        return $extra + ['l' => ['uk-ua' => $l], 'bg' => 'accent', 'closable' => 1, 'btn_style' => 'solid', 'blank' => 0];
    }

    // TEST-TBR-002 - verifies REQ-TBR-003, REQ-TBR-004, REQ-TBR-011 (text only / text + button; escaping; unsafe URLs dropped)
    public function test_text_only_and_text_with_button(): void
    {
        $now = strtotime('2026-10-15 12:00:00');

        $plain = \Ocs\Topbar::build($this->settings(['text' => 'Free <b>delivery</b>']), 'uk-ua', $now);
        $this->assertSame('Free &lt;b&gt;delivery&lt;/b&gt;', $plain['text']);
        $this->assertSame(null, $plain['button']);
        $this->assertSame('', $plain['href']);

        $full = \Ocs\Topbar::build($this->settings(['text' => 'Sale', 'url' => '/sale', 'btn_label' => 'Shop now', 'btn_url' => 'https://x.test/a?b=1&c=2']), 'uk-ua', $now);
        $this->assertSame('/sale', $full['href']);
        $this->assertSame('Shop now', $full['button']['label']);
        $this->assertSame('https://x.test/a?b=1&amp;c=2', $full['button']['href']);

        // a half-filled or unsafe button is not shown
        $this->assertSame(null, \Ocs\Topbar::build($this->settings(['text' => 'A', 'btn_label' => 'Go']), 'uk-ua', $now)['button']);
        $this->assertSame(null, \Ocs\Topbar::build($this->settings(['text' => 'A', 'btn_url' => '/x']), 'uk-ua', $now)['button']);
        $this->assertSame(null, \Ocs\Topbar::build($this->settings(['text' => 'A', 'btn_label' => 'Go', 'btn_url' => 'javascript:alert(1)']), 'uk-ua', $now)['button']);
        $this->assertSame('', \Ocs\Topbar::build($this->settings(['text' => 'A', 'url' => 'data:text/html,x']), 'uk-ua', $now)['href']);
    }

    // TEST-TBR-002 - verifies REQ-TBR-003 (no text for the language, or outside the date window: nothing is shown)
    public function test_language_and_dates(): void
    {
        $s = $this->settings(['text' => 'Hi'], ['from' => '2026-10-01', 'to' => '2026-10-31']);
        $this->assertSame(null, \Ocs\Topbar::build($s, 'en-gb', strtotime('2026-10-15')));
        $this->assertSame(null, \Ocs\Topbar::build($s, 'uk-ua', strtotime('2026-09-30 23:59:59')));
        $this->assertSame(null, \Ocs\Topbar::build($s, 'uk-ua', strtotime('2026-11-01 00:00:00')));
        $this->assertTrue(\Ocs\Topbar::build($s, 'uk-ua', strtotime('2026-10-31 23:59:00')) !== null);
        $this->assertSame(null, \Ocs\Topbar::build($this->settings(['text' => '   ']), 'uk-ua', time()));
    }

    // TEST-TBR-003 - verifies REQ-TBR-005 (preset and custom backgrounds, automatic text colour, invalid HEX falls back)
    public function test_background_and_contrast(): void
    {
        $now = time();
        $t = ['text' => 'Hi'];

        $this->assertSame('accent', \Ocs\Topbar::build($this->settings($t, ['bg' => 'nonsense']), 'uk-ua', $now)['bg']);

        $dark = \Ocs\Topbar::build($this->settings($t, ['bg' => 'custom', 'bg_custom' => '#C8102E']), 'uk-ua', $now);
        $this->assertSame('custom', $dark['bg']);
        $this->assertSame('--tb-bg:#c8102e;--tb-fg:#ffffff', $dark['style']);

        $light = \Ocs\Topbar::build($this->settings($t, ['bg' => 'custom', 'bg_custom' => '#ffd60a']), 'uk-ua', $now);
        $this->assertSame('--tb-bg:#ffd60a;--tb-fg:#1d1d1f', $light['style']);

        $bad = \Ocs\Topbar::build($this->settings($t, ['bg' => 'custom', 'bg_custom' => 'red;background:url(x)']), 'uk-ua', $now);
        $this->assertSame('accent', $bad['bg']);
        $this->assertSame('', $bad['style']);
    }

    // TEST-TBR-003 - verifies REQ-TBR-002 (the dismissal id changes with text, button and the close option)
    public function test_id_changes_with_content(): void
    {
        $a = \Ocs\Topbar::build($this->settings(['text' => 'One']), 'uk-ua', time());
        $b = \Ocs\Topbar::build($this->settings(['text' => 'Two']), 'uk-ua', time());
        $c = \Ocs\Topbar::build($this->settings(['text' => 'One'], ['closable' => 0]), 'uk-ua', time());
        $this->assertTrue($a['id'] !== $b['id']);
        $this->assertTrue($a['id'] !== $c['id']);
        $this->assertSame(10, strlen($a['id']));
    }

    // TEST-TBR-001 - verifies REQ-TBR-001, REQ-TBR-010 (install migration: disabled by default, event registered, idempotent)
    public function test_install_migration_defaults_to_disabled_and_registers_event(): void
    {
        $db = new SqliteDb();
        $db->createCoreSubset();
        $m = new Context($db, 'oc_', dirname(__DIR__, 3));
        $run = function () use ($m): void {
            $fn = require dirname(__DIR__, 3) . '/custom/extensions/ocs_topbar/db/001_install.php';
            $fn($m);
        };
        $run();
        $run();
        $this->assertSame('0', (string) $m->setting->get('module_ocs_topbar', 'module_ocs_topbar_status'));
        $this->assertTrue($m->extension->isRegistered('module', 'ocs_topbar'));
        $this->assertCount(1, $db->pdo->query("SELECT 1 FROM oc_event WHERE code = 'ocs_topbar_header_before'")->fetchAll());
    }
}
