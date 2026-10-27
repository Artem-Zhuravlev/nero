<?php
declare(strict_types=1);

namespace Ocs\Test;

require_once dirname(__DIR__, 3) . '/custom/extensions/ocs/upload/system/library/ocs/contacts.php';
require_once dirname(__DIR__, 3) . '/custom/extensions/ocs/upload/system/library/ocs/footer.php';

final class FooterTest extends TestCase
{
    private function data(): array
    {
        $d = ['text_information' => 'Інформація', 'text_service' => 'Служба', 'text_extra' => 'Додатково', 'text_account' => 'Кабінет'];

        foreach (\Ocs\Footer::$links as $key => $pair) {
            $d[$pair[0]] = 'https://s.test/' . $key;
            $d[$pair[1]] = 'T-' . $key;
        }

        return $d;
    }

    private function pages(): array
    {
        return [['id' => 3, 'title' => 'Про нас <b>', 'href' => 'https://s.test/i3'], ['id' => 4, 'title' => 'Доставка', 'href' => 'https://s.test/i4']];
    }

    // TEST-FMN-001 - verifies REQ-FMN-001 (sanitise: unticked and unknown keys; missing setting hides nothing and shows logos)
    public function test_sanitize_and_defaults(): void
    {
        $valid = \Ocs\Footer::validKeys([3, 4]);
        $c = \Ocs\Footer::sanitize(['show' => ['contact' => '1', 'info:3' => '1', 'bogus' => '1', 'info:99' => '1'], 'payments' => '1'], $valid);
        $this->assertTrue(!in_array('contact', $c['hide'], true) && !in_array('info:3', $c['hide'], true));
        $this->assertTrue(in_array('sitemap', $c['hide'], true) && in_array('info:4', $c['hide'], true));
        $this->assertTrue(!in_array('bogus', $c['hide'], true) && !in_array('info:99', $c['hide'], true), 'unknown keys never reach the setting');
        $this->assertSame(1, $c['payments']);
        $this->assertSame(0, \Ocs\Footer::sanitize('junk', $valid)['payments']);

        $v = \Ocs\Footer::view(null, $this->pages(), $this->data());
        $this->assertSame(true, $v['payments']);
        $this->assertSame(3, count($v['groups']));
    }

    // TEST-FMN-001 - verifies REQ-FMN-003 (three groups, merged extra column in the specified order, no separate account column)
    public function test_merged_column(): void
    {
        $v = \Ocs\Footer::view([], $this->pages(), $this->data());
        $this->assertSame(['information', 'service', 'extra'], array_column($v['groups'], 'key'));
        $this->assertSame(['T-manufacturer', 'T-voucher', 'T-affiliate', 'T-special', 'T-account', 'T-order', 'T-wishlist', 'T-newsletter'], array_column($v['groups'][2]['links'], 'text'));
        $this->assertSame('Додатково', $v['groups'][2]['title']);
        $this->assertSame('Про нас &lt;b&gt;', $v['groups'][0]['links'][0]['text'], 'escaped');
    }

    // TEST-FMN-001 - verifies REQ-FMN-004 (hidden links are not rendered, empty groups disappear)
    public function test_hiding(): void
    {
        $cfg = ['hide' => ['sitemap', 'contact', 'return', 'info:3', 'info:4'], 'payments' => 0];
        $v = \Ocs\Footer::view($cfg, $this->pages(), $this->data());
        $this->assertSame(['extra'], array_column($v['groups'], 'key'), 'information and service had nothing left');
        $this->assertSame(false, $v['payments']);

        $v = \Ocs\Footer::view(['hide' => ['special']], $this->pages(), $this->data());
        $this->assertSame(7, count($v['groups'][2]['links']));
    }
}
