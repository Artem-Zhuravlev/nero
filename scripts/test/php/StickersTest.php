<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\Migrate\Context;
use Ocs\Test\Support\SqliteDb;

require_once dirname(__DIR__, 3) . '/custom/extensions/ocs_stickers/upload/system/library/ocs/stickers.php';

final class StickersTest extends TestCase
{
    private function def(array $over = []): array
    {
        return array_merge([
            'sticker_id' => 1, 'text' => 'Hit', 'text_color' => '#ffffff', 'bg_color' => '#000000', 'position' => 'tr', 'priority' => 0,
            'rule_type' => 'manual', 'rule_value' => 0, 'show_pct' => 0, 'category_id' => 0, 'manufacturer_id' => 0,
        ], $over);
    }

    private function fact(array $over = []): array
    {
        return array_merge([
            'product_id' => 10, 'date_added' => '2026-10-20 10:00:00', 'quantity' => 5, 'price' => 100.0, 'special' => null,
            'manufacturer_id' => 3, 'sold' => 0, 'cats' => [20, 27], 'manual' => [],
        ], $over);
    }

    // TEST-STK-002 - verifies REQ-STK-004 (every rule type, scopes and manual assignment)
    public function test_rules(): void
    {
        $now = strtotime('2026-10-23 12:00:00');

        $this->assertFalse(\Ocs\Stickers::matches($this->def(), $this->fact(), $now), 'manual stickers need an assignment');
        $this->assertTrue(\Ocs\Stickers::matches($this->def(), $this->fact(['manual' => [1]]), $now));

        $new = $this->def(['rule_type' => 'new', 'rule_value' => 7]);
        $this->assertTrue(\Ocs\Stickers::matches($new, $this->fact(), $now));
        $this->assertFalse(\Ocs\Stickers::matches($new, $this->fact(['date_added' => '2026-10-01 00:00:00']), $now));
        $this->assertFalse(\Ocs\Stickers::matches($this->def(['rule_type' => 'new', 'rule_value' => 0]), $this->fact(), $now), 'N=0 never matches');

        $disc = $this->def(['rule_type' => 'discount']);
        $this->assertTrue(\Ocs\Stickers::matches($disc, $this->fact(['special' => 80.0]), $now));
        $this->assertFalse(\Ocs\Stickers::matches($disc, $this->fact(['special' => 100.0]), $now), 'special equal to price is no discount');
        $this->assertFalse(\Ocs\Stickers::matches($disc, $this->fact(), $now));

        $out = $this->def(['rule_type' => 'out_of_stock']);
        $this->assertTrue(\Ocs\Stickers::matches($out, $this->fact(['quantity' => 0]), $now));
        $this->assertFalse(\Ocs\Stickers::matches($out, $this->fact(), $now));

        $top = $this->def(['rule_type' => 'top_sales', 'rule_value' => 10]);
        $this->assertTrue(\Ocs\Stickers::matches($top, $this->fact(['sold' => 10]), $now));
        $this->assertFalse(\Ocs\Stickers::matches($top, $this->fact(['sold' => 9]), $now));

        $scoped = $this->def(['rule_type' => 'out_of_stock', 'category_id' => 99]);
        $this->assertFalse(\Ocs\Stickers::matches($scoped, $this->fact(['quantity' => 0]), $now), 'category scope');
        $this->assertTrue(\Ocs\Stickers::matches($this->def(['rule_type' => 'out_of_stock', 'category_id' => 27]), $this->fact(['quantity' => 0]), $now));
        $this->assertFalse(\Ocs\Stickers::matches($this->def(['rule_type' => 'out_of_stock', 'manufacturer_id' => 4]), $this->fact(['quantity' => 0]), $now), 'manufacturer scope');
        $this->assertTrue(\Ocs\Stickers::matches($this->def(['category_id' => 99]), $this->fact(['manual' => [1]]), $now), 'manual assignment ignores the scope');
    }

    // TEST-STK-002 - verifies REQ-STK-005 (priority wins, limit per corner) and REQ-STK-004 (-N% from old and new price)
    public function test_priority_limit_and_percent_text(): void
    {
        $now = strtotime('2026-10-23 12:00:00');
        $defs = [
            $this->def(['sticker_id' => 1, 'text' => 'Low', 'priority' => 1, 'position' => 'tl']),
            $this->def(['sticker_id' => 2, 'text' => 'High', 'priority' => 9, 'position' => 'tl']),
            $this->def(['sticker_id' => 3, 'text' => 'Mid', 'priority' => 5, 'position' => 'tl']),
            $this->def(['sticker_id' => 4, 'text' => 'Sale', 'priority' => 3, 'position' => 'tr', 'rule_type' => 'discount', 'show_pct' => 1]),
        ];
        $facts = [10 => $this->fact(['manual' => [1, 2, 3], 'price' => 200.0, 'special' => 150.0])];

        $r = \Ocs\Stickers::resolve($defs, $facts, $now, 2);
        $this->assertSame(['High', 'Mid'], array_map(static function (array $s): string { return $s['text']; }, $r[10]['tl']));
        $this->assertSame('-25%', $r[10]['tr'][0]['text']);
        $this->assertSame([], \Ocs\Stickers::resolve([], $facts, $now, 2));

        $none = \Ocs\Stickers::resolve($defs, [11 => $this->fact(['product_id' => 11])], $now, 2);
        $this->assertTrue(!isset($none[11]), 'a product without matches gets nothing');

        $unknown = \Ocs\Stickers::resolve([$this->def(['text' => 'Odd', 'position' => 'zz'])], [10 => $this->fact(['manual' => [1]])], $now, 2);
        $this->assertSame('Odd', $unknown[10]['tl'][0]['text'], 'an unknown position falls back to the top-left corner');
    }

    // TEST-STK-005 - verifies REQ-STK-011 (colours are validated before they reach a style attribute; contrast helper)
    public function test_colours(): void
    {
        $this->assertSame('#aabbcc', \Ocs\Stickers::safeColor('#ABC', '#000000'));
        $this->assertSame('#0071e3', \Ocs\Stickers::safeColor(' #0071E3 ', '#000000'));
        $this->assertSame('#123456', \Ocs\Stickers::safeColor('red; background:url(x)', '#123456'));
        $this->assertSame('#123456', \Ocs\Stickers::safeColor('#12345', '#123456'));
        $this->assertTrue(\Ocs\Stickers::contrast('#000000', '#ffffff') > 20.9);
        $this->assertTrue(\Ocs\Stickers::contrast('#ffffff', '#ffd60a') < 4.5, 'white on yellow is flagged');
        $this->assertTrue(\Ocs\Stickers::contrast('#1d1d1f', '#ffd60a') >= 4.5);
    }

    // TEST-STK-001 - verifies REQ-STK-001, REQ-EXT-031 (install: six built-in stickers with a name per language, tables, off by default, idempotent)
    public function test_install_seeds_builtin_stickers(): void
    {
        $db = new SqliteDb();
        $db->createCoreSubset();
        $m = new Context($db, 'oc_', dirname(__DIR__, 3));
        $fn = require dirname(__DIR__, 3) . '/custom/extensions/ocs_stickers/db/001_install.php';
        $fn($m);
        $fn($m);

        $this->assertCount(6, $db->pdo->query('SELECT 1 FROM oc_ocs_sticker')->fetchAll());
        $languages = (int) $db->pdo->query('SELECT COUNT(*) FROM oc_language')->fetchColumn();
        $this->assertTrue($languages >= 2);
        $this->assertCount(6 * $languages, $db->pdo->query('SELECT 1 FROM oc_ocs_sticker_description')->fetchAll());
        $this->assertSame('Новинка', (string) $db->pdo->query("SELECT d.name FROM oc_ocs_sticker_description d JOIN oc_ocs_sticker s ON s.sticker_id = d.sticker_id JOIN oc_language l ON l.language_id = d.language_id WHERE s.code = 'new' AND l.code = 'uk-ua'")->fetchColumn());
        $this->assertSame('0', (string) $m->setting->get('module_ocs_stickers', 'module_ocs_stickers_status'));
        $this->assertTrue($db->tableExists('oc_ocs_sticker_product'));
        $this->assertCount(8, $db->pdo->query("SELECT 1 FROM oc_event WHERE code LIKE 'ocs_stickers_%'")->fetchAll());
        $this->assertCount(1, $db->pdo->query("SELECT 1 FROM oc_modification WHERE code = 'ocs_stickers_product_form'")->fetchAll());
    }
}
