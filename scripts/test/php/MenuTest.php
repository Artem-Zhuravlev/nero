<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\Migrate\Context;
use Ocs\Test\Support\SqliteDb;

require_once dirname(__DIR__, 3) . '/custom/extensions/ocs_menu/upload/system/library/ocs/categorytree.php';

final class MenuTest extends TestCase
{
    private function rows(): array
    {
        return [
            ['category_id' => 3, 'parent_id' => 1, 'name' => 'Phones', 'sort_order' => 2],
            ['category_id' => 1, 'parent_id' => 0, 'name' => 'Electronics', 'sort_order' => 1],
            ['category_id' => 4, 'parent_id' => 3, 'name' => 'Cases', 'sort_order' => 1],
            ['category_id' => 5, 'parent_id' => 4, 'name' => 'Leather', 'sort_order' => 1],
            ['category_id' => 2, 'parent_id' => 0, 'name' => 'Books', 'sort_order' => 0],
            ['category_id' => 9, 'parent_id' => 77, 'name' => 'Orphan', 'sort_order' => 0],
            ['category_id' => 6, 'parent_id' => 1, 'name' => 'Laptops', 'sort_order' => 1],
        ];
    }

    // TEST-MNU-001 - verifies REQ-MNU-003, REQ-MNU-030 (tree by level limit, orphans dropped, paths and hrefs)
    public function test_build_limits_levels_and_drops_orphans(): void
    {
        $tree = \Ocs\CategoryTree::build($this->rows(), 3);
        $this->assertCount(2, $tree);
        $ids = array_map(static function (array $n): int { return $n['category_id']; }, \Ocs\CategoryTree::flatten($tree));
        $this->assertTrue(!in_array(9, $ids, true), 'orphan must be dropped');
        $this->assertTrue(in_array(5, $ids, false) === false, 'level 4 must be cut at 3 levels');
        $one = \Ocs\CategoryTree::build($this->rows(), 1);
        $this->assertSame([], $one[0]['children']);

        $deco = \Ocs\CategoryTree::decorate($tree, static function (string $p): string {
            return 'p=' . $p;
        });
        $electronics = null;
        foreach ($deco as $n) {
            if ($n['category_id'] === 1) {
                $electronics = $n;
            }
        }
        $this->assertSame('1', $electronics['path']);
        $phones = null;
        foreach ($electronics['children'] as $c) {
            if ($c['category_id'] === 3) {
                $phones = $c;
            }
        }
        $this->assertSame('p=1_3', $phones['href']);
        $this->assertSame('1_3_4', $phones['children'][0]['path']);
    }

    // verifies REQ-MNU-030 robustness: a parent cycle does not loop forever
    public function test_cycle_is_guarded(): void
    {
        $tree = \Ocs\CategoryTree::build([
            ['category_id' => 1, 'parent_id' => 2, 'name' => 'A'],
            ['category_id' => 2, 'parent_id' => 1, 'name' => 'B'],
        ], 3);
        $this->assertSame([], $tree);
    }

    // TEST-MNU-005 - verifies REQ-MNU-020 (active path marks ancestors and the current node)
    public function test_mark_active_and_path_ids(): void
    {
        $this->assertSame([1, 3, 4], \Ocs\CategoryTree::pathIds('1_3_4'));
        $this->assertSame([1, 4], \Ocs\CategoryTree::pathIds('1_x_4_0'));
        $tree = \Ocs\CategoryTree::sort(\Ocs\CategoryTree::build($this->rows(), 3), 'sort');
        $marked = \Ocs\CategoryTree::markActive($tree, [1, 3]);
        $this->assertSame('Books', $marked[0]['name']);
        $this->assertTrue(!$marked[0]['active']);
        $this->assertTrue($marked[1]['active'] && !$marked[1]['current']);
        $laptops = $marked[1]['children'][0];
        $phones = $marked[1]['children'][1];
        $this->assertSame('Laptops', $laptops['name']);
        $this->assertTrue($phones['active'] && $phones['current']);
    }

    // TEST-MNU-004 - verifies REQ-MNU-010 (sort by name or order; pick keeps the requested order)
    public function test_sort_and_pick(): void
    {
        $tree = \Ocs\CategoryTree::build($this->rows(), 2);
        $byName = \Ocs\CategoryTree::sort($tree, 'name');
        $this->assertSame('Books', $byName[0]['name']);
        $this->assertSame('Laptops', $byName[1]['children'][0]['name']);
        $picked = \Ocs\CategoryTree::pick($tree, [6, 999, 2]);
        $this->assertCount(2, $picked);
        $this->assertSame(6, $picked[0]['category_id']);
        $this->assertSame(2, $picked[1]['category_id']);
    }

    // TEST-MNU-002 - verifies REQ-MNU-002 (brand group: most products first, then name, limited per category)
    public function test_group_brands(): void
    {
        $rows = [
            ['category_id' => 1, 'manufacturer_id' => 5, 'name' => 'Zed', 'cnt' => 3],
            ['category_id' => 1, 'manufacturer_id' => 6, 'name' => 'Alpha', 'cnt' => 3],
            ['category_id' => 1, 'manufacturer_id' => 7, 'name' => 'Top', 'cnt' => 9],
            ['category_id' => 1, 'manufacturer_id' => 8, 'name' => 'Low', 'cnt' => 1],
            ['category_id' => 2, 'manufacturer_id' => 5, 'name' => 'Zed', 'cnt' => 2],
        ];
        $g = \Ocs\CategoryTree::groupBrands($rows, 3);
        $this->assertSame([7, 6, 5], array_map(static function (array $b): int { return $b['manufacturer_id']; }, $g[1]));
        $this->assertCount(1, $g[2]);
        $this->assertSame([], \Ocs\CategoryTree::groupBrands([], 3));
    }

    // verifies REQ-EXT-031 (brands off by default; migration 002 idempotent and keeps edited values)
    public function test_brands_migration(): void
    {
        $db = new SqliteDb();
        $db->createCoreSubset();
        $m = new Context($db, 'oc_', dirname(__DIR__, 3));
        $fn = require dirname(__DIR__, 3) . '/custom/extensions/ocs_mega_menu/db/002_brands.php';
        $fn($m);
        $this->assertSame('0', (string) $m->setting->get('module_ocs_mega_menu', 'module_ocs_mega_menu_brands'));
        $m->setting->set('module_ocs_mega_menu', 'module_ocs_mega_menu_brands_limit', 12);
        $fn($m);
        $this->assertSame('12', (string) $m->setting->get('module_ocs_mega_menu', 'module_ocs_mega_menu_brands_limit'));
    }

    // verifies REQ-MNU-001 (icon values: Font Awesome class vs image path)
    public function test_icon_kinds(): void
    {
        $this->assertSame('svg', \Ocs\CategoryTree::icon('fa-mobile')['type']);
        $this->assertSame('i-mobile', \Ocs\CategoryTree::icon('fa-mobile')['value']);
        $this->assertSame('image', \Ocs\CategoryTree::icon('catalog/icons/a.png')['type']);
        $this->assertSame('', \Ocs\CategoryTree::icon('  ')['type']);
        $this->assertSame('image', \Ocs\CategoryTree::icon('fa-"><script>')['type']);
    }

    // TEST-MNU-006 - verifies REQ-MNU-031, REQ-EXT-031 (all four install migrations run, modules disabled by default, table created, idempotent)
    public function test_install_migrations(): void
    {
        $db = new SqliteDb();
        $db->createCoreSubset();
        $m = new Context($db, 'oc_', dirname(__DIR__, 3));
        foreach (['ocs_menu', 'ocs_mega_menu', 'ocs_category_wall', 'ocs_category_menu'] as $code) {
            $fn = require dirname(__DIR__, 3) . "/custom/extensions/{$code}/db/001_install.php";
            $fn($m);
            $fn($m);
        }
        $this->assertTrue($db->tableExists('oc_ocs_menu_category'));
        foreach (['ocs_mega_menu', 'ocs_category_wall', 'ocs_category_menu'] as $code) {
            $this->assertTrue($m->extension->isRegistered('module', $code), $code);
            $this->assertSame('0', (string) $m->setting->get("module_{$code}", "module_{$code}_status"));
        }
        $this->assertCount(1, $db->pdo->query("SELECT 1 FROM oc_event WHERE code = 'ocs_mega_menu_menu_before'")->fetchAll());
    }
}
