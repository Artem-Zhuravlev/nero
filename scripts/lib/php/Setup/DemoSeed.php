<?php
declare(strict_types=1);

namespace Ocs\Setup;

use Ocs\Migrate\Context;

/**
 * Demo content for the storefront blocks (SPEC-039 REQ-VIS-010, SPEC-040 REQ-PKG-009): module switches, category icons,
 * home slides, home layout, tabbed shelf. Idempotent. Never creates or deletes catalogue data; it only touches ocs_* tables,
 * module settings and layout positions, and it relies on the stock OpenCart demo catalogue (category ids, catalog/demo images)
 * where present - missing categories/images are skipped.
 */
final class DemoSeed
{
    /** @var Context */
    private $m;
    /** @var callable */
    private $say;
    /** @var string|null docroot image dir (with trailing slash) used to skip slides whose picture is absent */
    private $imageDir;
    /** @var array<string, mixed> icons, tabs, banners, links from DemoCatalogue::homeHints() (SPEC-041 REQ-DCAT-008) */
    private $hints;

    /** @param array<string, mixed> $hints */
    public function __construct(Context $m, callable $say, ?string $imageDir = null, array $hints = [])
    {
        $this->hints = $hints;
        $this->m = $m;
        $this->say = $say;
        $this->imageDir = $imageDir;
    }

    private function log(string $line): void
    {
        ($this->say)($line);
    }

    public function run(): void
    {
        $m = $this->m;
        $db = $m->db;
        $p = $m->prefix;

        $this->log('modules on');
        foreach ([
            'module_ocs_home_slider' => ['module_ocs_home_slider_status' => 1, 'module_ocs_home_slider_tiles' => 1, 'module_ocs_home_slider_menu' => 1],
            'module_ocs_benefits' => ['module_ocs_benefits_status' => 1],
            'module_ocs_brand_strip' => ['module_ocs_brand_strip_status' => 1],
            'module_ocs_stickers' => ['module_ocs_stickers_status' => 1],
            'module_ocs_newsletter' => ['module_ocs_newsletter_status' => 1],
        ] as $code => $settings) {
            foreach ($settings as $key => $value) {
                $m->setting->set($code, $key, $value);
                $this->log("  setting {$key}");
            }
        }

        $this->log('category icons');
        $icons = ['20' => 'fa-desktop', '18' => 'fa-laptop', '25' => 'fa-puzzle-piece', '57' => 'fa-tablet', '17' => 'fa-cubes', '24' => 'fa-mobile', '33' => 'fa-camera', '34' => 'fa-music'];
        $popular = ['18', '24'];
        if (!empty($this->hints['icons'])) {
            $icons = [];
            $popular = [];
            foreach ($this->hints['icons'] as $id => [$icon, $pop]) {
                $icons[(string) $id] = $icon;
                if ($pop) {
                    $popular[] = (string) $id;
                }
            }
        }
        foreach ($icons as $id => $icon) {
            if ($db->rows('SELECT category_id FROM ' . $p . 'category WHERE category_id = ' . (int) $id)) {
                $db->exec('INSERT INTO ' . $p . "ocs_menu_category (category_id, icon, popular) VALUES ({$id}, '" . $db->escape($icon) . "', " . (in_array((string) $id, $popular, true) ? 1 : 0) . ') ON DUPLICATE KEY UPDATE icon = VALUES(icon), popular = VALUES(popular)');
                $this->log("  category {$id} {$icon}");
            }
        }

        $this->log('home slides');
        $banner = !empty($this->hints['banners']) ? $this->hints['banners'][0] : 'catalog/demo/banners/MacBookAir.jpg';
        if ($this->imageDir !== null && !is_file($this->imageDir . $banner)) {
            $this->log('  skipped: demo banners are not in image/catalog/demo');
        } elseif (!$db->rows('SELECT slide_id FROM ' . $p . 'ocs_slide LIMIT 1')) {
            $languages = $db->rows('SELECT language_id, code FROM ' . $p . 'language');
            $slides = [
                ['catalog/demo/banners/MacBookAir.jpg', '/index.php?route=product/special', ['uk-ua' => ['Акції тижня', 'Знижки до 30%'], 'en-gb' => ['Deals of the week', 'Up to 30% off']]],
                ['catalog/demo/banners/iPhone6.jpg', '/index.php?route=product/category&path=24', ['uk-ua' => ['Смартфони', 'Нові моделі'], 'en-gb' => ['Smartphones', 'New models']]],
                ['catalog/demo/banners/MacBookAir.jpg', '/index.php?route=product/category&path=18', ['uk-ua' => ['Ноутбуки', 'Знижки'], 'en-gb' => ['Laptops', 'Deals']]],
            ];
            if (!empty($this->hints['banners'])) {
                $slides = [];
                $titles = [['Смартфони', 'Нові моделі', 'Smartphones', 'New models'], ['Ноутбуки', 'Знижки до 15%', 'Laptops', 'Up to 15% off'], ['Аудіо', 'Хіти продажів', 'Audio', 'Best sellers']];
                foreach ($this->hints['banners'] as $k => $image) {
                    $t = $titles[$k % 3];
                    $slides[] = [$image, $this->hints['links'][$k] ?? '/', ['uk-ua' => [$t[0], $t[1]], 'en-gb' => [$t[2], $t[3]]]];
                }
            }
            foreach ($slides as $i => [$image, $link, $texts]) {
                $db->exec('INSERT INTO ' . $p . "ocs_slide (image, image_mobile, link, sort_order, status) VALUES ('" . $db->escape($image) . "', '', '" . $db->escape($link) . "', " . ($i + 1) . ', 1)');
                $id = $db->lastId();
                foreach ($languages as $language) {
                    $t = $texts[$language['code']] ?? $texts['en-gb'];
                    $db->exec('INSERT INTO ' . $p . "ocs_slide_description (slide_id, language_id, title, text) VALUES ({$id}, " . (int) $language['language_id'] . ", '" . $db->escape($t[0]) . "', '" . $db->escape($t[1]) . "')");
                }
            }
            $this->log('  ' . count($slides) . ' slides');
        }

        $this->log('home layout (layout 1)');
        foreach ([['ocs_home_slider', 'content_top', 1], ['ocs_benefits', 'content_top', 2], ['ocs_brand_strip', 'content_top', 4], ['ocs_newsletter', 'content_bottom', 5]] as [$code, $position, $sort]) {
            if (!$db->rows('SELECT layout_module_id FROM ' . $p . "layout_module WHERE layout_id = 1 AND code = '" . $db->escape($code) . "'")) {
                $db->exec('INSERT INTO ' . $p . "layout_module (layout_id, code, position, sort_order) VALUES (1, '" . $db->escape($code) . "', '{$position}', {$sort})");
                $this->log("  {$code}");
            }
        }

        $this->log('tabbed shelf');
        if (!$db->rows('SELECT module_id FROM ' . $p . "module WHERE code = 'ocs_product_carousel'")) {
            $setting = json_encode([
                'name' => 'Best in categories', 'status' => '1', 'mode' => 'newest', 'category_id' => '0', 'ids' => '', 'limit' => '10', 'show_all' => '1',
                'tabs' => !empty($this->hints['tabs']) ? $this->hints['tabs'] : "Ноутбуки|18|bestsellers\nСмартфони|24|newest\nПланшети|57|newest",
                'title' => ['1' => 'Найкраще [[в категоріях]]', '2' => 'Best [[in categories]]'],
            ], JSON_UNESCAPED_UNICODE);
            $db->exec('INSERT INTO ' . $p . "module (name, code, setting) VALUES ('Best in categories', 'ocs_product_carousel', '" . $db->escape($setting) . "')");
            $id = $db->lastId();
            $db->exec('INSERT INTO ' . $p . "layout_module (layout_id, code, position, sort_order) VALUES (1, 'ocs_product_carousel.{$id}', 'content_top', 5)");
            $this->log("  module {$id}");
        }
    }
}
