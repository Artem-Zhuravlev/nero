<?php
declare(strict_types=1);

namespace Ocs\Setup;

use Ocs\Migrate\Context;

/**
 * Demo catalogue (SPEC-041): categories, brands, attributes, options, products, reviews, information pages, stickers,
 * accessories, written through the core admin models. Every created id is registered in the setting
 * ocs_package.ocs_demo_catalogue so removal deletes exactly what was created and nothing else.
 */
final class DemoCatalogue
{
    public const SETTING_CODE = 'ocs_package';
    public const SETTING_KEY = 'ocs_demo_catalogue';
    /** previous values of the settings the demo wrote (SPEC-068 REQ-DCF-004, 008) */
    public const SETTINGS_KEY = 'ocs_demo_settings';

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
    /** @var array<int, string> language_id => full language code (uk-ua) */
    private $langCodes = [];
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
        $this->langCodes = [];
        foreach ($this->m->rows('SELECT language_id, code FROM {{PREFIX}}language ORDER BY language_id') as $row) {
            $short = strtolower(substr((string) $row['code'], 0, 2));
            $this->langs[(int) $row['language_id']] = in_array($short, ['uk', 'ru', 'en'], true) ? $short : 'en';
            $this->langCodes[(int) $row['language_id']] = (string) $row['code'];
            $this->langCodes[(int) $row['language_id']] = (string) $row['code'];
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
                $this->topUpProduct((int) $this->id('product', $p['code']), $p, $group);
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
                'model' => 'OCSDEMO-' . $p['code'], 'sku' => $p['sku'] ?? '', 'upc' => $p['upc'] ?? '', 'ean' => $p['ean'] ?? '', 'jan' => '', 'isbn' => '', 'mpn' => $p['mpn'] ?? '', 'location' => $p['location'] ?? '',
                'quantity' => $p['quantity'], 'minimum' => $p['minimum'] ?? 1, 'subtract' => 1, 'stock_status_id' => $stock[$p['state']],
                'date_available' => date('Y-m-d', time() - 86400), 'manufacturer_id' => $this->id('manufacturer', $p['brand']), 'shipping' => 1,
                'price' => $p['price'], 'points' => $p['points'] ?? 0, 'weight' => $p['weight'] ?? 0.5, 'weight_class_id' => $weightClass, 'length' => $p['dims'][0] ?? 0, 'width' => $p['dims'][1] ?? 0, 'height' => $p['dims'][2] ?? 0,
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
            foreach ($p['discounts'] ?? [] as $k => $discount) {
                $data['product_discount'][] = ['customer_group_id' => $group, 'quantity' => $discount['qty'], 'priority' => $k + 1, 'price' => $discount['price'], 'date_start' => '0000-00-00', 'date_end' => '0000-00-00'];
            }
            foreach ($p['discounts'] ?? [] as $k => $discount) {
                $data['product_discount'][] = ['customer_group_id' => $group, 'quantity' => $discount['qty'], 'priority' => $k + 1, 'price' => $discount['price'], 'date_start' => '0000-00-00', 'date_end' => '0000-00-00'];
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
                    $this->reviewExtra((int) $this->id('review', $key), $d['reviews'][($k + (int) $p['code']) % count($d['reviews'])]);
                    continue;
                }
                $r = $d['reviews'][($k + (int) $p['code']) % count($d['reviews'])];
                $reviewId = (int) $rev->addReview([
                    'author' => $r['author'], 'product_id' => $this->id('product', $p['code']), 'text' => $this->text($r['text'], 'uk') . ' ',
                    'rating' => $r['rating'], 'status' => 1, 'date_added' => date('Y-m-d H:i:s', time() - ($k + 1) * 5 * 86400),
                ]);
                $this->remember('review', $key, $reviewId);
                $this->reviewExtra($reviewId, $r);
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

        $this->log('stickers, accessories');
        $this->ocsTables();
        $this->log('custom fields: video, questions, menu banners, settings, requests');
        $this->customFields();

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
            foreach ($d['products'] as $prod) {
                $id = $this->id('product', $prod['code']);
                $codes = array_unique(array_merge($prod['sticker'] === 'hit' ? ['hit'] : [], $prod['stickers'] ?? []));
                foreach ($codes as $stickerCode) {
                    $sticker = $this->m->rows('SELECT sticker_id FROM {{PREFIX}}ocs_sticker WHERE code = ?', [$stickerCode]);
                    if ($sticker && $id && !$this->m->rows('SELECT 1 AS x FROM {{PREFIX}}ocs_sticker_product WHERE sticker_id = ? AND product_id = ?', [(int) $sticker[0]['sticker_id'], $id])) {
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
    }

    // ---- custom fields (SPEC-068) ----

    private function hasTable(string $table): bool
    {
        return $this->m->db->tableExists($this->m->prefix . $table);
    }

    /** fills columns added to older demo products and the quantity discounts */
    private function topUpProduct(int $id, array $p, int $group): void
    {
        $db = $this->m->db;
        $prefix = $this->m->prefix;
        if (!$db->columnExists($prefix . 'product', 'sku')) {
            return;
        }
        $row = $this->m->rows('SELECT sku FROM {{PREFIX}}product WHERE product_id = ?', [$id]);
        if (!$row || (string) $row[0]['sku'] !== '' || empty($p['sku'])) {
            return;
        }
        $this->m->sql('UPDATE {{PREFIX}}product SET sku = ?, mpn = ?, upc = ?, ean = ?, location = ?, minimum = ?, points = ?, weight = ?, length = ?, width = ?, height = ? WHERE product_id = ?', [
            $p['sku'], $p['mpn'], $p['upc'], $p['ean'], $p['location'], $p['minimum'] ?? 1, $p['points'] ?? 0, $p['weight'] ?? 0.5, $p['dims'][0] ?? 0, $p['dims'][1] ?? 0, $p['dims'][2] ?? 0, $id,
        ]);
        if ($this->hasTable('product_discount') && !empty($p['discounts']) && !$this->m->rows('SELECT 1 AS x FROM {{PREFIX}}product_discount WHERE product_id = ?', [$id])) {
            foreach ($p['discounts'] as $k => $discount) {
                $this->m->sql('INSERT INTO {{PREFIX}}product_discount (product_id, customer_group_id, quantity, priority, price, date_start, date_end) VALUES (?, ?, ?, ?, ?, ?, ?)', [$id, $group, $discount['qty'], $k + 1, $discount['price'], '0000-00-00', '0000-00-00']);
            }
        }
    }

    private function reviewExtra(int $reviewId, array $review): void
    {
        if (!$reviewId || !$this->hasTable('ocs_review_extra') || empty($review['pros']) || $this->m->rows('SELECT 1 AS x FROM {{PREFIX}}ocs_review_extra WHERE review_id = ?', [$reviewId])) {
            return;
        }
        $this->m->sql('INSERT INTO {{PREFIX}}ocs_review_extra (review_id, pros, cons) VALUES (?, ?, ?)', [$reviewId, $this->text($review['pros'], 'uk'), $this->text($review['cons'] ?? [], 'uk')]);
    }

    private function customFields(): void
    {
        $d = $this->data;
        $this->videosAndQuestions();
        $this->menuBanners();
        $this->settings($d['settings'] ?? []);
        $this->records($d['records'] ?? []);
    }

    private function videosAndQuestions(): void
    {
        $now = time();
        foreach ($this->data['products'] as $prod) {
            $id = $this->id('product', $prod['code']);
            if (!$id) {
                continue;
            }
            if (!empty($prod['video']) && $this->hasTable('ocs_product_video') && $this->id('video', $prod['code']) === null) {
                if (!$this->m->rows('SELECT 1 AS x FROM {{PREFIX}}ocs_product_video WHERE product_id = ?', [$id])) {
                    $this->m->sql('INSERT INTO {{PREFIX}}ocs_product_video (product_id, video_id) VALUES (?, ?)', [$id, $prod['video']]);
                    $this->remember('video', $prod['code'], $id);
                }
            }
            if (!$this->hasTable('ocs_question')) {
                continue;
            }
            foreach ($prod['questions'] ?? [] as $n => $q) {
                $key = $prod['code'] . '-' . $n;
                if ($this->id('question', $key) !== null) {
                    continue;
                }
                $this->m->sql('INSERT INTO {{PREFIX}}ocs_question (product_id, customer_id, name, email, text, answer, status, ip, date_added) VALUES (?, 0, ?, ?, ?, ?, ?, ?, ?)', [
                    $id, $q['name'], 'ask' . $prod['code'] . $n . '@example.test', $this->text($q['q'], 'uk'), $q['a'] ? $this->text($q['a'], 'uk') : '', (int) $q['status'], '127.0.0.1',
                    date('Y-m-d H:i:s', $now - (($n + 1) * 3 + (int) $prod['code'] % 5) * 86400),
                ]);
                $this->remember('question', $key, $this->m->db->lastId());
            }
        }
    }

    private function menuBanners(): void
    {
        if (!$this->hasTable('ocs_menu_category')) {
            return;
        }
        foreach ($this->data['categories'] as $c) {
            $id = $this->id('category', $c['code']);
            if (!$id || $this->id('menu', $c['code']) !== null || (empty($c['banner']) && empty($c['icon']))) {
                continue;
            }
            $image = empty($c['banner']) ? '' : "catalog/ocs-demo/banners/{$c['banner']['image']}.png";
            $url = empty($c['banner']) ? '' : '/index.php?route=product/category&path=' . (int) $this->id('category', $c['banner']['link']);
            if ($this->m->rows('SELECT 1 AS x FROM {{PREFIX}}ocs_menu_category WHERE category_id = ?', [$id])) {
                $this->m->sql('UPDATE {{PREFIX}}ocs_menu_category SET banner_image = ?, banner_url = ? WHERE category_id = ? AND banner_image = ?', [$image, $url, $id, '']);
            } else {
                $this->m->sql('INSERT INTO {{PREFIX}}ocs_menu_category (category_id, icon, popular, banner_image, banner_url) VALUES (?, ?, ?, ?, ?)', [$id, (string) ($c['icon'] ?? ''), !empty($c['popular']) ? 1 : 0, $image, $url]);
            }
            $this->remember('menu', $c['code'], $id);
        }
    }

    /**
     * Resolves the markers of the data file: {"@l": {field: {uk,ru,en}}} -> per language code and field, {"@lt": {uk,ru,en}} -> per language
     * code, "@info:code", "@cats:a,b", "@date:+N" in strings.
     *
     * @param mixed $v
     * @return mixed
     */
    private function resolve($v)
    {
        if (is_string($v)) {
            if (strncmp($v, '@cats:', 6) === 0) {
                $ids = [];
                foreach (explode(',', substr($v, 6)) as $code) {
                    if ($this->id('category', $code)) {
                        $ids[] = $this->id('category', $code);
                    }
                }
                return implode(',', $ids);
            }
            if (strncmp($v, '@date:', 6) === 0) {
                return date('Y-m-d', time() + (int) substr($v, 6) * 86400);
            }
            return (string) preg_replace_callback('/@info:([a-z]+)/', function (array $m): string {
                return (string) ($this->id('information', $m[1]) ?? 0);
            }, $v);
        }
        if (!is_array($v)) {
            return $v;
        }
        if (isset($v['@l']) && is_array($v['@l'])) {
            $out = [];
            foreach ($this->langs as $id => $key) {
                foreach ($v['@l'] as $field => $texts) {
                    $out[$this->langCodes[$id]][$field] = $this->resolve($this->text($texts, $key));
                }
            }
            return $out;
        }
        if (isset($v['@lt']) && is_array($v['@lt'])) {
            $out = [];
            foreach ($this->langs as $id => $key) {
                $out[$this->langCodes[$id]] = $this->resolve($this->text($v['@lt'], $key));
            }
            return $out;
        }
        $out = [];
        foreach ($v as $k => $item) {
            $out[$k] = $this->resolve($item);
        }
        return $out;
    }

    /** @param mixed $v null, '', 0, [] and arrays holding only such leaves count as empty */
    private static function isEmpty($v): bool
    {
        if (is_array($v)) {
            foreach ($v as $item) {
                if (!self::isEmpty($item)) {
                    return false;
                }
            }
            return true;
        }
        return $v === null || $v === '' || $v === '0' || $v === 0 || $v === false;
    }

    private function settings(array $rows): void
    {
        $raw = $this->m->setting->get(self::SETTING_CODE, self::SETTINGS_KEY);
        $written = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        foreach ($rows as $row) {
            $code = $row['code'];
            $key = $row['key'];
            if (isset($written["{$code}|{$key}"])) {
                continue;
            }
            $current = $this->m->setting->get($code, $key);
            if (!self::isEmpty($current)) {
                $this->log("  kept the existing {$key}");
                continue;
            }
            $this->m->setting->set($code, $key, $this->resolve($row['value']));
            $written["{$code}|{$key}"] = ['old' => $current, 'hash' => md5((string) json_encode($this->m->setting->get($code, $key)))];
        }
        $this->m->setting->set(self::SETTING_CODE, self::SETTINGS_KEY, json_encode($written, JSON_UNESCAPED_UNICODE));
    }

    private function restoreSettings(): void
    {
        $raw = $this->m->setting->get(self::SETTING_CODE, self::SETTINGS_KEY);
        $written = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        foreach ($written as $pair => $info) {
            [$code, $key] = explode('|', $pair, 2);
            if (md5((string) json_encode($this->m->setting->get($code, $key))) !== $info['hash']) {
                continue; // changed by the owner after the demo: keep
            }
            if ($info['old'] === null) {
                $this->m->setting->delete($code, $key);
            } else {
                $this->m->setting->set($code, $key, $info['old']);
            }
        }
        $this->m->setting->delete(self::SETTING_CODE, self::SETTINGS_KEY);
    }

    /** subscribers, back-in-stock subscriptions, pre-order requests and trade-in leads for the admin lists */
    private function records(array $r): void
    {
        $now = time();
        $lang = (int) array_keys($this->langs)[0];
        $code = strtolower((string) ($this->langCodes[$lang] ?? 'uk-ua'));
        $byState = function (string $state): array {
            $ids = [];
            foreach ($this->data['products'] as $p) {
                if ($p['state'] === $state && $this->id('product', $p['code'])) {
                    $ids[] = [(int) $this->id('product', $p['code']), $this->text($p['name'], 'en')];
                }
            }
            return $ids;
        };
        $inStock = $byState('in');
        $insert = function (string $type, int $n, string $sql, array $params): void {
            if (isset($this->map["{$type}:{$n}"])) {
                return;
            }
            $this->m->sql($sql, $params);
            $this->remember($type, (string) $n, $this->m->db->lastId());
        };
        if ($this->hasTable('ocs_subscriber')) {
            foreach ($r['subscribers'] ?? [] as $n => $s) {
                $insert('subscriber', $n, 'INSERT INTO {{PREFIX}}ocs_subscriber (email, language_id, confirmed, token, ip, date_added) VALUES (?, ?, ?, ?, ?, ?)',
                    [$s['email'], $lang, $s['confirmed'], md5($s['email']), '127.0.0.1', date('Y-m-d H:i:s', $now - ($n + 1) * 86400)]);
            }
        }
        $out = $byState('out');
        if ($this->hasTable('ocs_back_in_stock') && $out) {
            foreach ($r['backInStock'] ?? [] as $n => $s) {
                [$pid, $name] = $out[$n % count($out)];
                $insert('backinstock', $n, 'INSERT INTO {{PREFIX}}ocs_back_in_stock (product_id, product_name, option_key, email, language_code, status, date_added, date_notified) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [$pid, $name, '', $s['email'], $code, $s['status'], date('Y-m-d H:i:s', $now - ($n + 2) * 86400), $s['status'] ? date('Y-m-d H:i:s', $now - 86400) : null]);
            }
        }
        $pre = $byState('preorder');
        if ($this->hasTable('ocs_preorder') && $pre) {
            foreach ($r['preorders'] ?? [] as $n => $s) {
                [$pid, $name] = $pre[$n % count($pre)];
                $insert('preorder', $n, 'INSERT INTO {{PREFIX}}ocs_preorder (product_id, product_name, quantity, options, name, phone, email, comment, expected_date, order_id, status, date_added) VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?, 0, ?, ?)',
                    [$pid, $name, '', $s['name'], $s['phone'], $s['email'], $s['comment'], date('Y-m-d', $now + $s['days'] * 86400), $s['status'], date('Y-m-d H:i:s', $now - ($n + 1) * 86400)]);
            }
        }
        if ($this->hasTable('ocs_tradein_lead') && $inStock) {
            foreach ($r['leads'] ?? [] as $n => $s) {
                $insert('lead', $n, 'INSERT INTO {{PREFIX}}ocs_tradein_lead (product_id, language_id, name, phone, email, device, photos, status, ip, date_added) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$inStock[$n % count($inStock)][0], $lang, $s['name'], $s['phone'], $s['email'], $s['device'], '', $s['status'], '127.0.0.1', date('Y-m-d H:i:s', $now - ($n + 1) * 3600 * 7)]);
            }
        }
    }

    /** deletes the rows of the OCS tables this class created; core rows go through the core models */
    private function removeOcsRows(): void
    {
        $tables = ['video' => ['ocs_product_video', 'product_id'], 'question' => ['ocs_question', 'question_id'], 'menu' => ['ocs_menu_category', 'category_id'],
            'subscriber' => ['ocs_subscriber', 'subscriber_id'], 'backinstock' => ['ocs_back_in_stock', 'subscription_id'], 'preorder' => ['ocs_preorder', 'preorder_id'], 'lead' => ['ocs_tradein_lead', 'lead_id']];
        $reviews = [];
        foreach ($this->map as $key => $id) {
            if (strncmp($key, 'review:', 7) === 0) {
                $reviews[] = (int) $id;
            }
        }
        if ($reviews && $this->hasTable('ocs_review_extra')) {
            $this->m->db->exec('DELETE FROM ' . $this->m->prefix . 'ocs_review_extra WHERE review_id IN (' . implode(',', $reviews) . ')');
        }
        foreach ($tables as $type => [$table, $column]) {
            $ids = [];
            foreach ($this->map as $key => $id) {
                if (strncmp($key, $type . ':', strlen($type) + 1) === 0) {
                    $ids[] = (int) $id;
                }
            }
            if ($ids && $this->hasTable($table)) {
                $this->m->db->exec('DELETE FROM ' . $this->m->prefix . $table . " WHERE {$column} IN (" . implode(',', $ids) . ')');
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

        $this->removeOcsRows();
        $this->restoreSettings();
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
