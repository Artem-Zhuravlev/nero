<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\Migrate\Context;
use Ocs\Test\Support\SqliteDb;

require_once dirname(__DIR__, 3) . '/custom/extensions/ocs_credit/upload/system/library/ocs/credit.php';
require_once dirname(__DIR__, 3) . '/custom/extensions/ocs_tradein/upload/system/library/ocs/tradein.php';

final class CreditTradeinTest extends TestCase
{
    // TEST-CRD-001 - verifies REQ-CRD-001, REQ-CRD-002 (annuity formula; zero-rate plan = price / months; commission is financed)
    public function test_payment_formula(): void
    {
        $this->assertSame(50.0, \Ocs\Credit::payment(500, 10, 0));
        $this->assertSame(51.0, \Ocs\Credit::payment(500, 10, 0, 2));
        $this->assertSame('49.72', number_format(\Ocs\Credit::payment(500, 12, 30, 2), 2));
        $this->assertSame('88.85', number_format(\Ocs\Credit::payment(1000, 12, 12), 2), '1000 over 12 months at 12 % a year');
        $this->assertSame(0.0, \Ocs\Credit::payment(0, 12, 12));
        $this->assertSame(100.0, \Ocs\Credit::payment(100, 0, 0), 'months below 1 count as 1');
        $this->assertTrue(\Ocs\Credit::payment(1000, 12, 12) > \Ocs\Credit::payment(1000, 12, 0), 'interest raises the payment');
    }

    // TEST-CRD-001 - verifies REQ-CRD-002 (plans are hidden outside their price range; max 0 = no upper limit)
    public function test_plans_for_price(): void
    {
        $plans = [
            ['bank' => 'A', 'months' => 10, 'rate' => 0, 'fee' => 0, 'min' => 100, 'max' => 0],
            ['bank' => 'B', 'months' => 12, 'rate' => 30, 'fee' => 2, 'min' => 0, 'max' => 1000],
            ['bank' => 'C', 'months' => 6, 'rate' => 0, 'fee' => 0, 'min' => 5000, 'max' => 0],
        ];

        $this->assertSame(['A', 'B'], array_column(\Ocs\Credit::plansFor($plans, 500), 'bank'));
        $this->assertSame(['B'], array_column(\Ocs\Credit::plansFor($plans, 99), 'bank'));
        $this->assertSame(['A', 'C'], array_column(\Ocs\Credit::plansFor($plans, 6000), 'bank'));
        $this->assertSame([], \Ocs\Credit::plansFor($plans, 0));
        $this->assertSame([0, 1], array_column(\Ocs\Credit::plansFor($plans, 1000), 'index'), 'upper bound is inclusive and the original index is kept');
    }

    // TEST-CRD-001 - verifies REQ-CRD-001 (admin plan rows: validation, empty rows skipped)
    public function test_clean_plans(): void
    {
        $ok = \Ocs\Credit::cleanPlans([
            ['bank' => ' Mono  Bank ', 'months' => '12', 'rate' => '30', 'fee' => '2', 'min' => '', 'max' => '100000'],
            ['bank' => '', 'months' => '', 'rate' => '', 'fee' => '', 'min' => '', 'max' => ''],
        ]);
        $this->assertSame([], $ok['errors']);
        $this->assertCount(1, $ok['plans']);
        $this->assertSame('Mono Bank', $ok['plans'][0]['bank']);
        $this->assertSame(12, $ok['plans'][0]['months']);
        $this->assertSame(0.0, $ok['plans'][0]['min']);

        $this->assertTrue(array_key_exists('months', \Ocs\Credit::cleanPlans([['bank' => 'X', 'months' => '0']])['errors']), 'months');
        $this->assertTrue(array_key_exists('months', \Ocs\Credit::cleanPlans([['bank' => 'X', 'months' => '1.5']])['errors']), 'months');
        $this->assertTrue(array_key_exists('bank', \Ocs\Credit::cleanPlans([['bank' => '', 'months' => '6']])['errors']), 'bank');
        $this->assertTrue(array_key_exists('rate', \Ocs\Credit::cleanPlans([['bank' => 'X', 'months' => '6', 'rate' => '101']])['errors']), 'rate');
        $this->assertTrue(array_key_exists('fee', \Ocs\Credit::cleanPlans([['bank' => 'X', 'months' => '6', 'fee' => '-1']])['errors']), 'fee');
        $this->assertTrue(array_key_exists('range', \Ocs\Credit::cleanPlans([['bank' => 'X', 'months' => '6', 'min' => '500', 'max' => '100']])['errors']), 'range');
        $this->assertTrue(array_key_exists('count', \Ocs\Credit::cleanPlans(array_fill(0, 13, ['bank' => 'X', 'months' => '6']))['errors']), 'count');
    }

    // TEST-CRD-002 - verifies REQ-CRD-003 (the note that goes into the quick-order comment)
    public function test_note(): void
    {
        $this->assertSame('Plan: Mono, 12 mo., about 49.72 UAH', \Ocs\Credit::note('Plan: %bank%, %months% mo., about %pay%', 'Mono', 12, '49.72 UAH'));
    }

    // TEST-CRD-003 - verifies REQ-CRD-020 ($_FILES shapes: multiple, single, empty slots)
    public function test_normalize_uploads(): void
    {
        $multi = ['name' => ['a.png', ''], 'tmp_name' => ['/t/a', ''], 'size' => [10, 0], 'error' => [0, UPLOAD_ERR_NO_FILE]];
        $this->assertCount(1, \Ocs\Tradein::normalize($multi));
        $single = ['name' => 'a.png', 'tmp_name' => '/t/a', 'size' => 10, 'error' => 0];
        $this->assertCount(1, \Ocs\Tradein::normalize($single));
        $this->assertSame([], \Ocs\Tradein::normalize(null));
        $this->assertSame([], \Ocs\Tradein::normalize(['name' => '', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_NO_FILE]));
    }

    private function tmp(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ocs');
        file_put_contents($path, $bytes);

        return $path;
    }

    // TEST-CRD-003 - verifies REQ-CRD-020 (images only, whatever the name says; at most 3 files of 2 MB; must be a real upload)
    public function test_upload_checks(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $good = $this->tmp($png);
        $text = $this->tmp('<?php echo 1;');
        $yes = function () {
            return true;
        };
        $entry = function (string $path, int $error = 0) {
            return ['name' => 'x.png', 'tmp_name' => $path, 'size' => filesize($path), 'error' => $error];
        };

        $this->assertSame('', \Ocs\Tradein::check([$entry($good)], $yes)['error']);
        $this->assertSame('png', \Ocs\Tradein::check([$entry($good)], $yes)['files'][0]['ext']);
        $this->assertSame('type', \Ocs\Tradein::check([$entry($text)], $yes)['error'], 'a script named .png is refused');
        $this->assertSame('count', \Ocs\Tradein::check([$entry($good), $entry($good), $entry($good), $entry($good)], $yes)['error']);
        $this->assertSame('upload', \Ocs\Tradein::check([$entry($good)], function () {
            return false;
        })['error'], 'not an HTTP upload');
        $this->assertSame('upload', \Ocs\Tradein::check([$entry($good, UPLOAD_ERR_PARTIAL)], $yes)['error']);
        $this->assertSame('size', \Ocs\Tradein::check([$entry($good, UPLOAD_ERR_INI_SIZE)], $yes)['error']);
        $big = ['name' => 'b.png', 'tmp_name' => $good, 'size' => 2097153, 'error' => 0];
        $this->assertSame('size', \Ocs\Tradein::check([$big], $yes)['error']);
        $this->assertSame('', \Ocs\Tradein::check([], $yes)['error'], 'photos are optional');

        unlink($good);
        unlink($text);
    }

    // TEST-CRD-003 - verifies REQ-CRD-020 (stored names are random hex with a whitelisted extension; anything else is not served)
    public function test_stored_names(): void
    {
        $name = \Ocs\Tradein::storedName('jpg');
        $this->assertTrue(\Ocs\Tradein::validStoredName($name));
        $this->assertTrue(!\Ocs\Tradein::validStoredName('../../config.php'));
        $this->assertTrue(!\Ocs\Tradein::validStoredName(str_repeat('a', 32) . '.php'));
        $this->assertTrue(!\Ocs\Tradein::validStoredName(str_repeat('a', 31) . '.png'));
        $this->assertSame([str_repeat('a', 32) . '.png'], \Ocs\Tradein::parseNames(str_repeat('a', 32) . '.png,../x.png,'));
    }

    // TEST-CRD-004 - verifies REQ-CRD-021, REQ-EXT-031 (install: lead table, both modules off by default, events, idempotent)
    public function test_install_migrations(): void
    {
        $db = new SqliteDb();
        $db->createCoreSubset();
        $m = new Context($db, 'oc_', dirname(__DIR__, 3));

        foreach (['ocs_credit', 'ocs_tradein'] as $code) {
            $fn = require dirname(__DIR__, 3) . "/custom/extensions/{$code}/db/001_install.php";
            $fn($m);
            $fn($m);

            $this->assertTrue($m->extension->isRegistered('module', $code), $code);
        }

        $this->assertTrue($db->tableExists('oc_ocs_tradein_lead'));
        $this->assertSame('0', (string) $m->setting->get('module_ocs_credit', 'module_ocs_credit_status'));
        $this->assertSame('0', (string) $m->setting->get('module_ocs_tradein', 'module_ocs_tradein_status'));
        $this->assertCount(3, $db->pdo->query("SELECT code FROM oc_event WHERE code LIKE 'ocs_credit_%' OR code LIKE 'ocs_tradein_%'")->fetchAll());
    }
}
