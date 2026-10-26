<?php
declare(strict_types=1);

namespace Ocs\Test;

require_once dirname(__DIR__, 3) . '/custom/extensions/ocs/upload/system/library/ocs/contacts.php';

final class ContactsTest extends TestCase
{
    // TEST-FTR-002 - verifies REQ-FTR-002 (phones: tel: href with digits and a leading plus, label after a pipe, junk dropped, escaped text)
    public function test_phones(): void
    {
        $p = \Ocs\Contacts::phones("+380 (44) 123-45-67 | Sales\n\n0 800 50 00 00\nhello\n<b>+38 050 111 22 33</b>");
        $this->assertSame('tel:+380441234567', $p[0]['href']);
        $this->assertSame('+380 (44) 123-45-67', $p[0]['text']);
        $this->assertSame('Sales', $p[0]['label']);
        $this->assertSame('tel:0800500000', $p[1]['href']);
        $this->assertSame(3, count($p), 'a line without digits is dropped');
        $this->assertTrue(strpos($p[2]['text'], '<b>') === false, 'text is escaped');
    }

    // TEST-SET-001 - verifies REQ-SET-002, REQ-SET-020 (only whitelisted icons and safe URLs survive, titles per language, sorted, capped)
    public function test_sanitize_links(): void
    {
        $rows = [
            ['icon' => 'telegram', 'kind' => 'messenger', 'title' => ['uk-ua' => ' Telegram ', 'en-gb' => 'TG'], 'url' => 'https://t.me/shop', 'sort' => '2'],
            ['icon' => 'instagram', 'kind' => 'bogus', 'title' => ['uk-ua' => 'Instagram'], 'url' => 'https://instagram.com/shop', 'sort' => '1'],
            ['icon' => 'evil', 'title' => [], 'url' => 'https://x.test'],
            ['icon' => 'site', 'title' => ['uk-ua' => 'x'], 'url' => 'javascript:alert(1)'],
            ['icon' => 'site', 'title' => ['uk-ua' => 'x'], 'url' => 'data:text/html;base64,AAAA'],
            ['icon' => 'viber', 'title' => ['uk-ua' => 'Viber'], 'url' => 'viber://chat?number=%2B380501112233', 'sort' => '3'],
            'junk',
        ];
        $clean = \Ocs\Contacts::sanitize($rows, ['uk-ua', 'en-gb']);
        $this->assertSame(['instagram', 'telegram', 'viber'], array_column($clean, 'icon'));
        $this->assertSame('social', $clean[0]['kind']);
        $this->assertSame('Telegram', $clean[1]['title']['uk-ua']);
        $this->assertSame('', $clean[0]['title']['en-gb'], 'missing title is empty, not undefined');
        $this->assertSame([], \Ocs\Contacts::sanitize('nope', ['uk-ua']));
        $many = array_fill(0, 40, ['icon' => 'site', 'title' => [], 'url' => 'https://a.test']);
        $this->assertSame(20, count(\Ocs\Contacts::sanitize($many, ['uk-ua'])));
    }

    // TEST-FTR-001, TEST-SET-003 - verifies REQ-FTR-001, REQ-SET-003 (view: language fallback, icons, kinds, escaping, no empty data)
    public function test_view(): void
    {
        $contact = ['uk-ua' => ['address' => 'Київ, <script>1</script>', 'hours' => "Пн-Пт 9-18\nСб 10-15", 'phones' => '+380441234567'], 'en-gb' => ['address' => '', 'hours' => '', 'phones' => '']];
        $links = [
            ['icon' => 'telegram', 'kind' => 'messenger', 'title' => ['uk-ua' => 'Telegram'], 'url' => 'https://t.me/shop'],
            ['icon' => 'facebook', 'kind' => 'social', 'title' => ['uk-ua' => '', 'en-gb' => ''], 'url' => 'https://facebook.com/shop'],
            ['icon' => 'site', 'kind' => 'social', 'title' => [], 'url' => 'javascript:1'],
        ];
        $v = \Ocs\Contacts::view($contact, $links, 'en-gb', 'uk-ua');
        $this->assertTrue(strpos($v['address'], '<script>') === false && strpos($v['address'], '&lt;script&gt;') !== false);
        $this->assertSame('Пн-Пт 9-18<br>Сб 10-15', $v['hours'], 'empty value falls back to the default language, line breaks become <br>');
        $this->assertSame('tel:+380441234567', $v['phones'][0]['href']);
        $this->assertSame(2, count($v['links']), 'unsafe URL dropped');
        $this->assertSame('i-telegram', $v['messengers'][0]['icon']);
        $this->assertSame('Facebook', $v['social'][0]['title'], 'no title: the icon name');
        $empty = \Ocs\Contacts::view(null, null, 'uk-ua', 'uk-ua');
        $this->assertTrue($empty['address'] === '' && $empty['phones'] === [] && $empty['links'] === []);
    }

    // TEST-SET-004 - verifies REQ-SET-004 (every admin icon has a symbol in the storefront sprite)
    public function test_icons_exist_in_sprite(): void
    {
        $sprite = file_get_contents(dirname(__DIR__, 3) . '/custom/theme/ocs/template/ocs/sprite.twig');
        foreach (\Ocs\Contacts::$icons as $name => $symbol) {
            $this->assertTrue(strpos($sprite, 'id="' . $symbol . '"') !== false, "sprite lacks $symbol for $name");
        }
    }
}
