<?php
declare(strict_types=1);

namespace Ocs\Setup;

use Ocs\Migrate\Context;

/**
 * Demo catalogue (SPEC-041): categories, brands, attributes, options, products, reviews, information pages, stickers,
 * accessories, cities and stores, written through the core admin models. Every created id is registered in the setting
 * ocs_package.ocs_demo_catalogue so removal deletes exactly what was created and nothing else.
 */
final class DemoCatalogue
{
    public const SETTING_CODE = 'ocs_package';
    public const SETTING_KEY = 'ocs_demo_catalogue';

    /** removal order: leaves first */
    private const REMOVE_ORDER = ['review', 'product', 'information', 'category', 'manufacturer', 'attribute', 'attribute_group', 'option'];

    /** @var Context */
    private $m;
    /** @var array<string, mixed> */
    private $data;
    /** @var callable fn(string $route): object */
    private $models;
    /** @var string docroot image dir with trailing slash */
    private $imageDir;
    /** @var callable */
    private $say;
    /** @var array<string, int> language code (uk|ru|en) key per language_id */
    private $langs = [];
    /** @var array<string, int> */
    private $map = [];

    /**
     * @param array<string, mixed> $data decoded catalogue.json
     * @param callable $models fn(string $route): object returning the loaded admin model (e.g. 'catalog/product')
     */
    public function __construct(Context $m, array $data, callable $models, string $imageDir, callable $say)
    {
        $this->m = $m;
        $this->data = $data;
        $this->models = $models;
        $this->imageDir = rtrim(str_replace('\\', '/', $imageDir), '/') . '/';
        $this->say = $say;
    }

    public static function load(string $file): array
    {
        $json = json_decode((string) file_get_contents($file), true);
        if (!is_array($json) || empty($json['products'])) {
            throw new \RuntimeException('demo catalogue data is missing or invalid');
        }
        return $json;
    }

    private function log(string $line): void
    {
        ($this->say)($line);
    }

    private function model(string $route)
    {
        return ($this->models)($route);
    }

    // ---- registry ----

    private function readMap(): void
    {
        $raw = $this->m->setting->get(self::SETTING_CODE, self::SETTING_KEY);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $this->map = is_array($decoded) ? array_map('intval', $decoded) : [];
    }

    private function saveMap(): void
    {
        $this->m->setting->set(self::SETTING_CODE, self::SETTING_KEY, json_encode($this->map));
    }

    private function id(string $type, string $code): ?int
    {
        return $this->map["{$type}:{$code}"] ?? null;
    }

    private function remember(string $type, string $code, int $id): int
    {
        $this->map["{$type}:{$code}"] = $id;
        $this->saveMap();
        return $id;
    }

    public function exists(): bool
    {
        $this->readMap();
        return (bool) $this->map;
    }

    // ---- helpers ----

    /** @param array<string,string> $texts keyed uk|ru|en */
    private function text(array $texts, string $key): string
    {
        return (string) ($texts[$key] ?? $texts['en'] ?? reset($texts));
    }

    /** @param array<string,string> $texts @return array<int, string> language_id => text */
    private function perLanguage(array $texts): array
    {
        $out = [];
        foreach ($this->langs as $id => $key) {
            $out[$id] = $this->text($texts, $key);
        }
        return $out;
    }

    private function keywords(string $keyword): array
    {
        $out = [];
        foreach (array_keys($this->langs) as $id) {
            $out[$id] = $keyword;
        }
        return [0 => $out];
    }

    private static function slug(string $s): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-');
    }

    private function loadLanguages(): void
    {
        $this->langs = [];
        foreach ($this->m->rows('SELECT language_id, code FROM {{PREFIX}}language ORDER BY language_id') as $row) {
            $short = strtolower(substr((string) $row['code'], 0, 2));
            $this->langs[(int) $row['language_id']] = in_array($short, ['uk', 'ru', 'en'], true) ? $short : 'en';
        }
        if (!$this->langs) {
            throw new \RuntimeException('no languages are installed');
        }
    }

    private function firstId(string $table, string $column, int $prefer): int
    {
        $rows = $this->m->rows("SELECT {$column} AS id FROM {{PREFIX}}{$table} ORDER BY {$column}");
        foreach ($rows as $r) {
            if ((int) $r['id'] === $prefer) {
                return $prefer;
            }
        }
        return $rows ? (int) $rows[0]['id'] : 0;
    }

    // ---- create ----

    /** @return array{products:int, categories:int} */
    public function create(): array
    {
        $this->loadLanguages();
        $this->readMap();
        $d = $this->data;

        $stock = ['in' => $this->firstId('stock_status', 'stock_status_id', 7), 'out' => $this->firstId('stock_status', 'stock_status_id', 5), 'preorder' => $this->firstId('stock_status', 'stock_status_id', 8)];
        $weightClass = $this->firstId('weight_class', 'weight_class_id', 1);
        $lengthClass = $this->firstId('length_class', 'length_class_id', 1);
        $group = (int) ($this->m->setting->get('config', 'config_customer_group_id') ?: 1);

        $this->log('attribute groups, attributes, options');
        $ag = $this->model('catalog/attribute_group');
        foreach ($d['attributeGroups'] as $g) {
            if ($this->id('attribute_group', $g['code']) === null) {
                $this->remember('attribute_group', $g['code'], (int) $ag->addAttributeGroup([
                    'sort_order' => $g['sort'],
                    'attribute_group_description' => $this->wrap($this->perLanguage($g['name'])),
                ]));
            }
        }
        $attr = $this->model('catalog/attribute');
        foreach ($d['attributes'] as $i => $a) {
            if ($this->id('attribute', $a['code']) === null) {
                $this->remember('attribute', $a['code'], (int) $attr->addAttribute([
                    'attribute_group_id' => $this->id('attribute_group', $a['group']),
                    'sort_order' => $i + 1,
                    'attribute_description' => $this->wrap($this->perLanguage($a['name'])),
                ]));
            }
        }
        $opt = $this->model('catalog/option');
        foreach ($d['options'] as $i => $o) {
            if ($this->id('option', $o['code']) !== null) {
                continue;
            }
            $values = [];
            foreach ($o['values'] as $j => $v) {
                $values[] = ['option_value_id' => '', 'image' => '', 'sort_order' => $j + 1, 'option_value_description' => $this->wrap($this->perLanguage($v['name']))];
            }
            $optionId = (int) $opt->addOption([
                'type' => $o['type'], 'sort_order' => $i + 1,
                'option_description' => $this->wrap($this->perLanguage($o['name'])),
                'option_value' => $values,
            ]);
            $this->remember('option', $o['code'], $optionId);
            // option values get ids on insert: read them back in sort order
            $rows = $this->m->rows('SELECT option_value_id FROM {{PREFIX}}option_value WHERE option_id = ? ORDER BY sort_order, option_value_id', [$optionId]);
            foreach ($o['values'] as $j => $v) {
                $this->map["optionvalue:{$o['code']}:{$v['code']}"] = (int) $rows[$j]['option_value_id'];
            }
            $this->saveMap();
        }

        $this->log('manufacturers');
        $man = $this->model('catalog/manufacturer');
        foreach ($d['brands'] as $i => $b) {
            if ($this->id('manufacturer', $b['code']) !== null) {
                continue;
            }
            $this->remember('manufacturer', $b['code'], (int) $man->addManufacturer([
                'name' => $b['name'], 'sort_order' => ($i + 1) * 10, 'noindex' => 1, 'image' => "catalog/ocs-demo/brands/{$b['code']}.png",
                'manufacturer_description' => $this->describe(['name' => $b['name'], 'description' => '']),
                'manufacturer_store' => [0],
                'manufacturer_seo_url' => $this->keywords('demo-' . $b['code']),
            ]));
        }

        $this->log('categories');
        $cat = $this->model('catalog/category');
        foreach ($d['categories'] as $i => $c) {
            if ($this->id('category', $c['code']) !== null) {
                continue;
            }
            $parent = $c['parent'] ? (int) $this->id('category', $c['parent']) : 0;
            $text = ['name' => $this->nameMap($c['name']), 'description' => ''];
            $this->remember('category', $c['code'], (int) $cat->addCategory([
                'parent_id' => $parent, 'top' => $parent ? 0 : 1, 'column' => 1, 'sort_order' => ($i + 1) * 10, 'status' => 1, 'noindex' => 0,
                'image' => $parent ? '' : "catalog/ocs-demo/categories/{$c['code']}.png",
                'category_description' => $this->describe($text),
                'category_filter' => [],
                'category_store' => [0],
                'category_seo_url' => $this->keywords('demo-' . $c['code']),
                'category_layout' => [0 => ''],
            ]));
        }

        $this->log('products');
        $prod = $this->model('catalog/product');
        $created = 0;
        $old = date('Y-m-d H:i:s', time() - 90 * 86400);
        foreach ($d['products'] as $i => $p) {
            if ($this->id('product', $p['code']) !== null) {
                continue;
            }
            $categories = [(int) $this->id('category', $p['category'])];
            foreach ($p['categories'] as $parentCode) {
                $categories[] = (int) $this->id('category', $parentCode);
            }
            $attributes = [];
            foreach ($p['attrs'] as $code => $value) {
                $texts = is_array($value) ? $value : ['uk' => $value, 'ru' => $value, 'en' => $value];
                $attributes[] = ['attribute_id' => $this->id('attribute', $code), 'product_attribute_description' => $this->wrap($this->perLanguage($texts), 'text')];
            }
            $options = [];
            foreach ($p['options'] as $code => $optionCode) {
                $option = $this->optionDef($optionCode);
                $values = [];
                foreach ($option['values'] as $v) {
                    $values[] = [
                        'option_value_id' => $this->map["optionvalue:{$optionCode}:{$v['code']}"], 'product_option_value_id' => '', 'quantity' => 50, 'subtract' => 0,
                        'price' => abs($v['price']), 'price_prefix' => '+', 'points' => 0, 'points_prefix' => '+', 'weight' => 0, 'weight_prefix' => '+',
                    ];
                }
                $options[] = ['product_option_id' => '', 'name' => $this->text($option['name'], 'en'), 'option_id' => $this->id('option', $optionCode), 'type' => $option['type'], 'required' => 1, 'product_option_value' => $values];
            }
            $images = [];
            for ($n = 2; $n <= $p['images']; $n++) {
                $images[] = ['image' => "catalog/ocs-demo/products/{$p['code']}-{$n}.png", 'sort_order' => $n];
            }
            $name = $this->text($p['name'], 'en');
            $data = [
                'model' => 'OCSDEMO-' . $p['code'], 'sku' => '', 'upc' => '', 'ean' => '', 'jan' => '', 'isbn' => '', 'mpn' => '', 'location' => '',
                'quantity' => $p['quantity'], 'minimum' => 1, 'subtract' => 1, 'stock_status_id' => $stock[$p['state']],
                'date_available' => date('Y-m-d', time() - 86400), 'manufacturer_id' => $this->id('manufacturer', $p['brand']), 'shipping' => 1,
                'price' => $p['price'], 'points' => 0, 'weight' => 0.5, 'weight_class_id' => $weightClass, 'length' => 0, 'width' => 0, 'height' => 0,
                'length_class_id' => $lengthClass, 'status' => 1, 'tax_class_id' => 0, 'sort_order' => $i + 1, 'noindex' => 0,
                'image' => "catalog/ocs-demo/products/{$p['code']}-1.png",
                'product_description' => $this->describe(['name' => $this->nameMap($p['name']), 'description' => $p['description'], 'tag' => $name]),
                'product_store' => [0], 'product_category' => $categories, 'main_category_id' => $categories[0],
                'product_attribute' => $attributes, 'product_option' => $options, 'product_image' => $images,
                'product_seo_url' => $this->keywords('demo-' . self::slug($name) . '-' . $p['code']),
                'product_layout' => [0 => ''], 'product_filter' => [], 'product_download' => [], 'product_related' => [],
            ];
            if ($p['special']) {
                $data['product_special'] = [['customer_group_id' => $group, 'priority' => 1, 'price' => $p['special'], 'date_start' => '0000-00-00', 'date_end' => '0000-00-00']];
            }
            $id = (int) $prod->addProduct($data);
            $this->remember('product', $p['code'], $id);
            if (empty($p['isNew'])) {
                $this->m->sql('UPDATE {{PREFIX}}product SET date_added = ?, date_modified = ? WHERE product_id = ?', [$old, $old, $id]);
            }
            $created++;
        }

        $this->log('related products, reviews, information');
        foreach ($d['products'] as $p) {
            foreach ($p['related'] as $relatedCode) {
                $a = $this->id('product', $p['code']);
                $b = $this->id('product', $relatedCode);
                if ($a && $b && !$this->m->rows('SELECT 1 AS x FROM {{PREFIX}}product_related WHERE product_id = ? AND related_id = ?', [$a, $b])) {
                    $this->m->sql('INSERT INTO {{PREFIX}}product_related (product_id, related_id) VALUES (?, ?)', [$a, $b]);
                }
            }
        }
        $rev = $this->model('catalog/review');
        foreach ($d['products'] as $p) {
            for ($k = 0; $k < $p['reviews']; $k++) {
                $key = $p['code'] . '-' . $k;
                if ($this->id('review', $key) !== null) {
                    continue;
                }
                $r = $d['reviews'][($k + (int) $p['code']) % count($d['reviews'])];
                $this->remember('review', $key, (int) $rev->addReview([
                    'author' => $r['author'], 'product_id' => $this->id('product', $p['code']), 'text' => $this->text($r['text'], 'uk') . ' ',
                    'rating' => $r['rating'], 'status' => 1, 'date_added' => date('Y-m-d H:i:s', time() - ($k + 1) * 5 * 86400),
                ]));
            }
        }
        $info = $this->model('catalog/information');
        foreach ($d['information'] as $i => $page) {
            if ($this->id('information', $page['code']) !== null) {
                continue;
            }
            $this->remember('information', $page['code'], (int) $info->addInformation([
                'sort_order' => $i + 1, 'bottom' => $page['bottom'], 'status' => 1, 'noindex' => 0,
                'information_description' => $this->describe(['title' => $this->nameMap($page['title']), 'description' => $page['text']], true),
                'information_store' => [0],
                'information_seo_url' => $this->keywords('demo-' . $page['code']),
                'information_layout' => [0 => ''],
            ]));
        }

        $this->log('stickers, accessories, cities, stores');
        $this->ocsTables();

        return ['products' => $created, 'categories' => count($d['categories'])];
    }

    /** @param array<int,string> $values @return array<int, array<string,string>> */
    private function wrap(array $values, string $field = 'name'): array
    {
        $out = [];
        foreach ($values as $id => $v) {
            $out[$id] = [$field => $v];
        }
        return $out;
    }

    /** @param array<string,string> $names @return array<int,string> */
    private function nameMap(array $names): array
    {
        return $this->perLanguage($names);
    }

    /**
     * Full description rows (name/title, description, tag, meta_*) for every installed language.
     *
     * @param array<string, mixed> $fields name|title (array per language or string), description (string or per-language map), tag
     * @return array<int, array<string,string>>
     */
    private function describe(array $fields, bool $information = false): array
    {
        $out = [];
        foreach ($this->langs as $id => $key) {
            $label = $information ? 'title' : 'name';
            $name = is_array($fields[$label] ?? null) ? ($fields[$label][$id] ?? '') : (string) ($fields[$label] ?? '');
            $desc = $fields['description'] ?? '';
            if (is_array($desc)) {
                $desc = $this->text($desc, $key);
            }
            $row = [$label => $name, 'description' => $this->localised($desc, $key), 'tag' => (string) ($fields['tag'] ?? ''), 'meta_title' => $name, 'meta_h1' => '', 'meta_description' => '', 'meta_keyword' => ''];
            $out[$id] = $row;
        }
        return $out;
    }

    /** descriptions in the data are either a plain string or a {uk,ru,en} map */
    private function localised($desc, string $key): string
    {
        return is_array($desc) ? $this->text($desc, $key) : (string) $desc;
    }

    private function optionDef(string $code): array
    {
        foreach ($this->data['options'] as $o) {
            if ($o['code'] === $code) {
                return $o;
            }
        }
        throw new \RuntimeException("unknown option {$code}");
    }

    private function ocsTables(): void
    {
        $d = $this->data;
        $db = $this->m->db;
        $p = $this->m->prefix;
        if ($db->tableExists($p . 'ocs_sticker_product')) {
            $sticker = $this->m->rows('SELECT sticker_id FROM {{PREFIX}}ocs_sticker WHERE code = ?', ['hit']);
            if ($sticker) {
                foreach ($d['products'] as $prod) {
                    $id = $this->id('product', $prod['code']);
                    if ($prod['sticker'] === 'hit' && $id && !$this->m->rows('SELECT 1 AS x FROM {{PREFIX}}ocs_sticker_product WHERE sticker_id = ? AND product_id = ?', [(int) $sticker[0]['sticker_id'], $id])) {
                        $this->m->sql('INSERT INTO {{PREFIX}}ocs_sticker_product (sticker_id, product_id) VALUES (?, ?)', [(int) $sticker[0]['sticker_id'], $id]);
                    }
                }
            }
        }
        if ($db->tableExists($p . 'ocs_product_accessory')) {
            foreach ($d['products'] as $prod) {
                foreach ($prod['accessories'] as $k => $code) {
                    $a = $this->id('product', $prod['code']);
                    $b = $this->id('product', $code);
                    if ($a && $b && !$this->m->rows('SELECT 1 AS x FROM {{PREFIX}}ocs_product_accessory WHERE product_id = ? AND accessory_id = ?', [$a, $b])) {
                        $this->m->sql('INSERT INTO {{PREFIX}}ocs_product_accessory (product_id, accessory_id, sort_order) VALUES (?, ?, ?)', [$a, $b, $k + 1]);
                    }
                }
            }
        }
        if (!$db->tableExists($p . 'ocs_city')) {
            return;
        }
        $hasDefault = (bool) $this->m->rows('SELECT 1 AS x FROM {{PREFIX}}ocs_city WHERE is_default = 1');
        foreach ($d['cities'] as $i => $c) {
            if ($this->id('city', $c['code']) !== null) {
                continue;
            }
            $this->m->sql('INSERT INTO {{PREFIX}}ocs_city (sort_order, is_default, status) VALUES (?, ?, 1)', [($i + 1) * 10, !$hasDefault && !empty($c['default']) ? 1 : 0]);
            $cityId = $db->lastId();
            $this->remember('city', $c['code'], $cityId);
            foreach ($this->langs as $lid => $key) {
                $this->m->sql('INSERT INTO {{PREFIX}}ocs_city_description (city_id, language_id, name) VALUES (?, ?, ?)', [$cityId, $lid, $this->text($c['name'], $key)]);
            }
            foreach ($c['delivery'] as $dl) {
                $this->m->sql('INSERT INTO {{PREFIX}}ocs_city_delivery (city_id, method, price, free_from, days_min, days_max, cutoff, status) VALUES (?, ?, ?, ?, ?, ?, ?, 1)', [$cityId, $dl['method'], $dl['price'], $dl['freeFrom'], $dl['daysMin'], $dl['daysMax'], $dl['cutoff']]);
                foreach ($this->langs as $lid => $key) {
                    $this->m->sql('INSERT INTO {{PREFIX}}ocs_city_delivery_description (city_id, method, language_id, note) VALUES (?, ?, ?, ?)', [$cityId, $dl['method'], $lid, $this->text($dl['note'], $key)]);
                }
            }
        }
        foreach ($d['stores'] as $i => $s) {
            if ($this->id('store', $s['code']) !== null) {
                continue;
            }
            $this->m->sql('INSERT INTO {{PREFIX}}ocs_store (city_id, phone, map_link, latitude, longitude, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, 1)', [$this->id('city', $s['city']), $s['phone'], '', $s['lat'], $s['lng'], $i + 1]);
            $storeId = $db->lastId();
            $this->remember('store', $s['code'], $storeId);
            foreach ($this->langs as $lid => $key) {
                $this->m->sql('INSERT INTO {{PREFIX}}ocs_store_description (store_id, language_id, name, address, hours) VALUES (?, ?, ?, ?, ?)', [$storeId, $lid, $this->text($s['name'], $key), $this->text($s['address'], $key), $this->text($s['hours'], $key)]);
            }
        }
    }

    // ---- remove ----

    /** @return array{removed:int} */
    public function remove(): array
    {
        $this->readMap();
        $removed = 0;
        $db = $this->m->db;
        $p = $this->m->prefix;

        $productIds = [];
        foreach ($this->map as $key => $id) {
            if (strncmp($key, 'product:', 8) === 0) {
                $productIds[] = $id;
            }
        }
        $in = $productIds ? implode(',', array_map('intval', $productIds)) : '0';
        foreach (['ocs_sticker_product' => 'product_id', 'ocs_product_accessory' => 'product_id'] as $table => $column) {
            if ($db->tableExists($p . $table)) {
                $db->exec("DELETE FROM {$p}{$table} WHERE {$column} IN ({$in})");
            }
        }
        if ($db->tableExists($p . 'ocs_product_accessory')) {
            $db->exec("DELETE FROM {$p}ocs_product_accessory WHERE accessory_id IN ({$in})");
        }
        $db->exec("DELETE FROM {$p}product_related WHERE product_id IN ({$in}) OR related_id IN ({$in})");

        foreach ($this->map as $key => $id) {
            if (strncmp($key, 'store:', 6) === 0 && $db->tableExists($p . 'ocs_store')) {
                $db->exec("DELETE FROM {$p}ocs_store WHERE store_id = " . (int) $id);
                $db->exec("DELETE FROM {$p}ocs_store_description WHERE store_id = " . (int) $id);
                $db->exec("DELETE FROM {$p}ocs_store_stock WHERE store_id = " . (int) $id);
                $removed++;
            } elseif (strncmp($key, 'city:', 5) === 0 && $db->tableExists($p . 'ocs_city')) {
                foreach (['ocs_city', 'ocs_city_description', 'ocs_city_delivery', 'ocs_city_delivery_description'] as $table) {
                    $db->exec("DELETE FROM {$p}{$table} WHERE city_id = " . (int) $id);
                }
                $removed++;
            }
        }

        $this->loadLanguages();
        $methods = ['review' => ['catalog/review', 'deleteReview'], 'product' => ['catalog/product', 'deleteProduct'], 'information' => ['catalog/information', 'deleteInformation'],
            'category' => ['catalog/category', 'deleteCategory'], 'manufacturer' => ['catalog/manufacturer', 'deleteManufacturer'], 'attribute' => ['catalog/attribute', 'deleteAttribute'],
            'attribute_group' => ['catalog/attribute_group', 'deleteAttributeGroup'], 'option' => ['catalog/option', 'deleteOption']];
        foreach (self::REMOVE_ORDER as $type) {
            $ids = [];
            foreach ($this->map as $key => $id) {
                if (strncmp($key, $type . ':', strlen($type) + 1) === 0) {
                    $ids[] = $id;
                }
            }
            if ($type === 'category') {
                rsort($ids); // children were created after parents
            }
            [$route, $fn] = $methods[$type];
            foreach ($ids as $id) {
                $this->model($route)->$fn($id);
                $removed++;
            }
        }
        $this->removeImages();
        $this->m->sql('DELETE FROM {{PREFIX}}setting WHERE `code` = ? AND `key` = ?', [self::SETTING_CODE, self::SETTING_KEY]);
        $this->map = [];
        $this->log("removed {$removed} demo record(s) and the demo images");
        return ['removed' => $removed];
    }

    private function removeImages(): void
    {
        foreach ([$this->imageDir . 'catalog/ocs-demo', $this->imageDir . 'cache/catalog/ocs-demo'] as $dir) {
            self::rmTree($dir);
        }
    }

    private static function rmTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $path = $dir . '/' . $f;
            is_dir($path) ? self::rmTree($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    // ---- hints for the home page demo content (REQ-DCAT-008) ----

    /**
     * @return array{icons: array<int, array{0:string,1:int}>, tabs: string, banners: string[], links: array<int,string>}
     */
    public function homeHints(): array
    {
        $this->readMap();
        $icons = [];
        $tabs = [];
        foreach ($this->data['categories'] as $c) {
            $id = $this->id('category', $c['code']);
            if (!$id || !empty($c['parent']) || empty($c['icon'])) {
                continue;
            }
            $icons[$id] = [(string) $c['icon'], !empty($c['popular']) ? 1 : 0];
            if (count($tabs) < 3) {
                $tabs[] = $this->text($c['name'], 'uk') . '|' . $id . '|' . (count($tabs) === 0 ? 'bestsellers' : 'newest');
            }
        }
        $links = [];
        $banners = [];
        foreach ($this->data['banners'] as $b) {
            $banners[] = "catalog/ocs-demo/banners/{$b['code']}.png";
            $links[] = '/index.php?route=product/category&path=' . (int) $this->id('category', $b['link']);
        }
        return ['icons' => $icons, 'tabs' => implode("\n", $tabs), 'banners' => $banners, 'links' => $links];
    }
}
