<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\Migrate\Context;
use Ocs\Test\Support\SqliteDb;

require_once dirname(__DIR__, 3) . '/custom/extensions/ocs_newsletter/upload/system/library/ocs/newsletter.php';

final class HomeBlocksTest extends TestCase
{
    // TEST-HOM-005 - verifies REQ-HOM-030 (e-mail normalisation: trimmed, lower-cased, invalid and over-long rejected)
    public function test_newsletter_email(): void
    {
        $this->assertSame('a@b.com', \Ocs\Newsletter::email('  A@B.com '));
        $this->assertSame('', \Ocs\Newsletter::email('not-an-email'));
        $this->assertSame('', \Ocs\Newsletter::email(str_repeat('a', 100) . '@b.com'));
        $this->assertSame('', \Ocs\Newsletter::email(''));
    }

    // TEST-HOM-005 - verifies REQ-HOM-030 (tokens are 32 hex chars; anything else is refused)
    public function test_newsletter_token(): void
    {
        $t = \Ocs\Newsletter::token();
        $this->assertTrue(\Ocs\Newsletter::validToken($t));
        $this->assertTrue(!\Ocs\Newsletter::validToken("abc' OR 1=1"));
        $this->assertTrue(!\Ocs\Newsletter::validToken(''));
        $this->assertTrue(!\Ocs\Newsletter::validToken(strtoupper($t)));
    }

    // TEST-HOM-005 - verifies REQ-HOM-030 (CSV export quotes cells and defuses spreadsheet formulas)
    public function test_newsletter_csv_row(): void
    {
        $this->assertSame("\"a@b.com\",\"1\"\r\n", \Ocs\Newsletter::csvRow(['a@b.com', 1]));
        $this->assertSame("\"say \"\"hi\"\"\"\r\n", \Ocs\Newsletter::csvRow(['say "hi"']));
        $this->assertSame("\"'=SUM(A1)\",\"'@x\"\r\n", \Ocs\Newsletter::csvRow(['=SUM(A1)', '@x']));
    }

    // TEST-HOM-002 - verifies REQ-HOM-001, REQ-HOM-010, REQ-HOM-020, REQ-HOM-030, REQ-HOM-040, REQ-EXT-031 (install migrations: tables, disabled by default, idempotent)
    public function test_install_migrations(): void
    {
        $db = new SqliteDb();
        $db->createCoreSubset();
        $m = new Context($db, 'oc_', dirname(__DIR__, 3));

        foreach (['ocs_home_slider', 'ocs_brand_strip', 'ocs_product_carousel', 'ocs_newsletter', 'ocs_seo_text'] as $code) {
            $fn = require dirname(__DIR__, 3) . "/custom/extensions/{$code}/db/001_install.php";
            $fn($m);
            $fn($m);

            $this->assertTrue($m->extension->isRegistered('module', $code), $code);
        }

        $this->assertTrue($db->tableExists('oc_ocs_slide'));
        $this->assertTrue($db->tableExists('oc_ocs_slide_description'));
        $this->assertTrue($db->tableExists('oc_ocs_subscriber'));
        $this->assertSame('0', (string) $m->setting->get('module_ocs_home_slider', 'module_ocs_home_slider_status'));
        $this->assertSame('0', (string) $m->setting->get('module_ocs_brand_strip', 'module_ocs_brand_strip_status'));
        $this->assertSame('0', (string) $m->setting->get('module_ocs_newsletter', 'module_ocs_newsletter_status'));
        $this->assertSame('0', (string) $m->setting->get('module_ocs_seo_text', 'module_ocs_seo_text_status'));
    }
}
