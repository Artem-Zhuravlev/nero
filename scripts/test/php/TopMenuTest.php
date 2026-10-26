<?php
declare(strict_types=1);

namespace Ocs\Test;

require_once dirname(__DIR__, 3) . '/custom/extensions/ocs/upload/system/library/ocs/contacts.php';
require_once dirname(__DIR__, 3) . '/custom/extensions/ocs/upload/system/library/ocs/topmenu.php';

final class TopMenuTest extends TestCase
{
    // TEST-TOP-001 - verifies REQ-TOP-001 (sanitise: switches, titles per language cut to 60, only existing and ticked pages, sorted, capped)
    public function test_sanitize(): void
    {
        $raw = [
            'enabled' => '1', 'sale' => '1', 'blog' => '', 'contacts' => '1',
            'title' => ['uk-ua' => '  Покупцям  ', 'en-gb' => str_repeat('x', 100), 'ru-ru' => 'dropped'],
            'pages' => [
                '4' => ['on' => '1', 'sort' => '20'], '3' => ['on' => '1', 'sort' => '10'], '5' => ['sort' => '1'], '99' => ['on' => '1', 'sort' => '0'], 'x' => ['on' => '1'], '6' => 'junk',
            ],
        ];
        $c = \Ocs\TopMenu::sanitize($raw, ['uk-ua', 'en-gb'], [3, 4, 5, 6]);
        $this->assertSame([3, 4], $c['pages'], 'unticked, unknown and junk rows are dropped, order by sort');
        $this->assertSame('Покупцям', $c['title']['uk-ua']);
        $this->assertSame(60, mb_strlen($c['title']['en-gb'], 'UTF-8'));
        $this->assertTrue(!isset($c['title']['ru-ru']));
        $this->assertSame([1, 0, 1, 1], [$c['enabled'], $c['blog'], $c['sale'], $c['contacts']]);
        $this->assertSame(0, \Ocs\TopMenu::sanitize('junk', ['uk-ua'], [])['enabled']);
        $many = ['pages' => array_fill_keys(range(1, 30), ['on' => 1, 'sort' => 1])];
        $this->assertSame(12, count(\Ocs\TopMenu::sanitize($many, [], range(1, 30))['pages']));
    }

    // TEST-TOP-002 - verifies REQ-TOP-002 (view: only what is switched on and exists; escaped; active marker; title fallback; nothing -> null)
    public function test_view(): void
    {
        $cfg = ['enabled' => 1, 'contacts' => 1, 'blog' => 1, 'sale' => 1, 'title' => ['uk-ua' => '', 'en-gb' => 'Info'], 'pages' => [3, 4, 7]];
        $titles = [3 => 'Доставка <i>', 4 => 'Оплата'];
        $links = ['contacts' => ['href' => 'https://s.test/contact', 'title' => 'Контакти'], 'sale' => ['href' => 'https://s.test/special', 'title' => 'Акції'], 'pages' => [3 => 'https://s.test/i3', 4 => 'https://s.test/i4']];
        $v = \Ocs\TopMenu::view($cfg, 'uk-ua', 'en-gb', 'Покупцям', $titles, $links, 'information:4');
        $this->assertSame('Info', $v['title'], 'empty language title falls back to the default language');
        $this->assertSame(null, $v['blog'], 'no blog link available');
        $this->assertSame('Акції', $v['sale']['title']);
        $this->assertSame(3, count($v['pages']), 'two pages and Contacts; the unknown page 7 is skipped');
        $this->assertSame('Доставка &lt;i&gt;', $v['pages'][0]['title']);
        $this->assertTrue($v['pages'][1]['active'] && !$v['pages'][0]['active']);
        $this->assertSame('Контакти', $v['pages'][2]['title']);
        $off = $cfg;
        $off['enabled'] = 0;
        $this->assertSame(null, \Ocs\TopMenu::view($off, 'uk-ua', 'en-gb', 'x', $titles, $links, ''));
        $bad = $links;
        $bad['sale']['href'] = 'javascript:alert(1)';
        $this->assertSame('', \Ocs\TopMenu::view($cfg, 'uk-ua', 'en-gb', 'x', $titles, $bad, '')['sale']['href'], 'unsafe link is emptied');
        $empty = ['enabled' => 1, 'contacts' => 0, 'blog' => 0, 'sale' => 0, 'pages' => []];
        $this->assertSame(null, \Ocs\TopMenu::view($empty, 'uk-ua', 'en-gb', 'x', $titles, $links, ''), 'nothing to show renders nothing');
        $this->assertSame('Покупцям', \Ocs\TopMenu::view(['enabled' => 1, 'sale' => 1, 'title' => []] + $empty, 'uk-ua', 'en-gb', 'Покупцям', $titles, $links, '')['title']);
    }
}
