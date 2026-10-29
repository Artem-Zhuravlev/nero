<?php
declare(strict_types=1);

namespace Ocs\Test;

require_once dirname(__DIR__, 3) . '/custom/extensions/ocs_product_extras/upload/system/library/ocs/reviewextra.php';

final class ReviewExtraTest extends TestCase
{
    // TEST-REV-003 - verifies REQ-REV-003 (pros / cons: entity-decoded, tags stripped, one line, 255 characters, junk becomes '')
    public function test_clean(): void
    {
        $this->assertSame('Гучний звук', \Ocs\ReviewExtra::clean('  Гучний   звук '));
        $this->assertSame('alert(1) bold', \Ocs\ReviewExtra::clean('&lt;script&gt;alert(1)&lt;/script&gt; <b>bold</b>'));
        $this->assertSame('a b', \Ocs\ReviewExtra::clean("a\r\n\tb"));
        $this->assertSame(255, mb_strlen(\Ocs\ReviewExtra::clean(str_repeat('щ', 400)), 'UTF-8'));
        $this->assertSame('', \Ocs\ReviewExtra::clean(null));
        $this->assertSame('', \Ocs\ReviewExtra::clean(['x']) === '' ? '' : 'array');
    }

    // TEST-REV-031 - verifies REQ-REV-031 (admin reply: tags stripped, line breaks kept, 2000 characters, junk becomes '')
    public function test_clean_reply(): void
    {
        $this->assertSame("Дякуємо!\nГарного дня", \Ocs\ReviewExtra::cleanReply("  Дякуємо!\r\n<b>Гарного</b>   дня "));
        $this->assertSame(2000, mb_strlen(\Ocs\ReviewExtra::cleanReply(str_repeat('щ', 3000)), 'UTF-8'));
        $this->assertSame('', \Ocs\ReviewExtra::cleanReply(['x']));
    }
}
