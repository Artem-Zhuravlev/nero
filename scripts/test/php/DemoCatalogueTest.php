<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\Migrate\Context;
use Ocs\Setup\DemoCatalogue;
use Ocs\Test\Support\SqliteDb;

/** Records model calls and hands out ids, like the core admin models do. */
final class FakeAdminModel
{
    /** @var array<int, array{0:string, 1:array}> */
    public static $calls = [];
    /** @var int */
    public static $next = 100;
    /** @var \PDO|null */
    public static $pdo;

    public function __call(string $name, array $args)
    {
        self::$calls[] = [$name, $args];
        if ($name === 'addOption') {
            $id = ++self::$next;
            foreach ($args[0]['option_value'] as $i => $v) {
                self::$pdo->exec("INSERT INTO oc_option_value (option_id, sort_order) VALUES ({$id}, " . (int) $v['sort_order'] . ')');
            }
            return $id;
        }
        if (strncmp($name, 'add', 3) === 0) {
            return ++self::$next;
        }
        return null;
    }
}

// SPEC-041: demo catalogue builder, run against the committed catalogue.json with fake admin models.
final class DemoCatalogueTest extends TestCase
{
    /** @var SqliteDb */
    private $db;
    /** @var Context */
    private $m;
    /** @var string */
    private $images;

    protected function setUp(): void
    {
        $this->db = new SqliteDb();
        $this->db->createCoreSubset();
        foreach ([
            'CREATE TABLE oc_stock_status (stock_status_id INTEGER, language_id INTEGER, name TEXT)',
            'CREATE TABLE oc_weight_class (weight_class_id INTEGER)',
            'CREATE TABLE oc_length_class (length_class_id INTEGER)',
            'CREATE TABLE oc_option_value (option_value_id INTEGER PRIMARY KEY AUTOINCREMENT, option_id INTEGER, sort_order INTEGER)',
            'CREATE TABLE oc_product (product_id INTEGER, date_added TEXT, date_modified TEXT)',
            'CREATE TABLE oc_product_related (product_id INTEGER, related_id INTEGER)',
            'INSERT INTO oc_stock_status VALUES (7, 1, "in"), (5, 1, "out"), (8, 1, "pre")',
            'INSERT INTO oc_weight_class VALUES (1)',
            'INSERT INTO oc_length_class VALUES (1)',
        ] as $sql) {
            $this->db->pdo->exec($sql);
        }
        FakeAdminModel::$calls = [];
        FakeAdminModel::$next = 100;
        FakeAdminModel::$pdo = $this->db->pdo;
        $this->m = new Context($this->db, 'oc_', dirname(__DIR__, 3));
        $this->images = sys_get_temp_dir() . '/ocs-demo-img-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->images . 'catalog/ocs-demo/products', 0777, true);
        file_put_contents($this->images . 'catalog/ocs-demo/products/001-1.png', 'x');
    }

    protected function tearDown(): void
    {
        foreach (['catalog/ocs-demo/products/001-1.png'] as $f) {
            if (is_file($this->images . $f)) {
                unlink($this->images . $f);
            }
        }
        foreach (['catalog/ocs-demo/products', 'catalog/ocs-demo', 'catalog', ''] as $d) {
            if (is_dir($this->images . $d)) {
                rmdir(rtrim($this->images . $d, '/'));
            }
        }
    }

    private function catalogue(): DemoCatalogue
    {
        $data = DemoCatalogue::load(dirname(__DIR__, 3) . '/custom/catalog/demo/catalogue.json');
        return new DemoCatalogue($this->m, $data, static function (string $route) {
            return new FakeAdminModel();
        }, $this->images, static function (string $line): void {
        });
    }

    /** @return array<int, array> arguments of every call to $method */
    private function calls(string $method): array
    {
        return array_values(array_map(static function (array $c): array {
            return $c[1];
        }, array_filter(FakeAdminModel::$calls, static function (array $c) use ($method): bool {
            return $c[0] === $method;
        })));
    }

    // TEST-DCAT-003 - verifies REQ-DCAT-002, REQ-DCAT-005 (every installed language gets texts, core models are used)
    public function test_create_builds_everything_through_models_for_all_languages(): void
    {
        $r = $this->catalogue()->create();
        $this->assertSame(42, $r['products']);
        $products = $this->calls('addProduct');
        $this->assertSame(42, count($products));
        $this->assertSame(19, count($this->calls('addCategory')));
        $this->assertSame(8, count($this->calls('addManufacturer')));
        $first = $products[0][0];
        $this->assertSame([1, 2], array_keys($first['product_description']));
        $this->assertSame('OCSDEMO-001', $first['model']);
        $this->assertTrue(strpos($first['product_description'][1]['description'], 'смартфон') !== false);
        $this->assertTrue(strpos($first['product_description'][2]['description'], 'smartphone') !== false);
        foreach (['model', 'sku', 'quantity', 'stock_status_id', 'price', 'status', 'manufacturer_id', 'weight_class_id', 'product_store', 'product_seo_url'] as $key) {
            $this->assertTrue(array_key_exists($key, $first), "product data has {$key}");
        }
        $this->assertSame('demo-', substr($first['product_seo_url'][0][1], 0, 5));
    }

    // TEST-DCAT-001 - verifies REQ-DCAT-007 (the data exercises the theme)
    public function test_catalogue_exercises_theme_features(): void
    {
        $this->catalogue()->create();
        $products = array_map(static function (array $c): array {
            return $c[0];
        }, $this->calls('addProduct'));
        $this->assertTrue(count(array_filter($products, static function (array $p): bool {
            return isset($p['product_special']);
        })) >= 5, 'specials');
        $this->assertTrue(count(array_filter($products, static function (array $p): bool {
            return $p['quantity'] === 0 && $p['stock_status_id'] === 5;
        })) >= 2, 'out of stock');
        $this->assertTrue(count(array_filter($products, static function (array $p): bool {
            return $p['quantity'] === 0 && $p['stock_status_id'] === 8;
        })) >= 1, 'pre-order');
        $this->assertTrue(count(array_filter($products, static function (array $p): bool {
            return count($p['product_image']) >= 3 && count($p['product_attribute']) >= 3 && $p['product_option'] !== [];
        })) >= 5, 'gallery 4+, attributes 3+, options');
        $inPhones = array_filter($products, static function (array $p): bool {
            return count($p['product_category']) >= 2;
        });
        $this->assertTrue(count($inPhones) >= 16, 'a category with >= 16 products');
        $deep = 0;
        foreach ($this->calls('addCategory') as [$c]) {
            $deep = max($deep, $c['parent_id'] > 0 ? 1 : 0);
        }
        $this->assertSame(1, $deep);
        $this->assertTrue(count($this->calls('addReview')) >= 15, 'reviews');
    }

    // TEST-DCAT-003 - verifies REQ-DCAT-004 (idempotent)
    public function test_second_create_adds_nothing(): void
    {
        $this->catalogue()->create();
        $calls = count(FakeAdminModel::$calls);
        $r = $this->catalogue()->create();
        $this->assertSame(0, $r['products']);
        $this->assertSame($calls, count(FakeAdminModel::$calls));
    }

    // TEST-DCAT-003 - verifies REQ-DCAT-003 (removal walks the registry, children before parents, and clears it)
    public function test_remove_deletes_registered_records_only(): void
    {
        $cat = $this->catalogue();
        $cat->create();
        FakeAdminModel::$calls = [];
        $removed = $cat->remove();
        $this->assertTrue($removed['removed'] >= 42 + 19 + 8);
        $this->assertSame(42, count($this->calls('deleteProduct')));
        $this->assertSame(19, count($this->calls('deleteCategory')));
        $ids = array_map(static function (array $a): int {
            return $a[0];
        }, $this->calls('deleteCategory'));
        $sorted = $ids;
        rsort($sorted);
        $this->assertSame($sorted, $ids);
        $this->assertSame(0, (int) $this->db->pdo->query("SELECT COUNT(*) FROM oc_setting WHERE `key` = 'ocs_demo_catalogue'")->fetchColumn());
        $this->assertFalse($this->catalogue()->exists());
        $this->assertFalse(is_dir($this->images . 'catalog/ocs-demo'));
    }

    // TEST-DCAT-005 - verifies REQ-DCAT-008 (home blocks point at demo categories once the catalogue exists)
    public function test_home_hints_use_demo_categories(): void
    {
        $cat = $this->catalogue();
        $cat->create();
        $h = $cat->homeHints();
        $this->assertTrue(count($h['icons']) >= 5);
        $this->assertTrue(substr($h['tabs'], 0, 1) !== '' && substr_count($h['tabs'], "\n") === 2);
        $this->assertSame(3, count($h['banners']));
        $this->assertTrue(strpos($h['links'][0], 'path=') !== false);
    }
}
