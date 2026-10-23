<?php
declare(strict_types=1);

// SPEC-039 REQ-VIS-010: php -d extension=mysqli scripts/seed-demo.php [--config path]
// Switches on and fills the storefront blocks of a LOCAL stand so the pages can be judged with data: category icons and popular
// flags, home slides (hero + two promo tiles), benefits, category menu and tabbed shelf on the home page, quick view, stickers.
// Idempotent; refuses to run unless APP_ENV=local. Never run it on a live shop.

require_once __DIR__ . '/lib/php/cli.php';

use Ocs\Migrate\Context;

$root = dirname(__DIR__);
$args = ocs_parse_args($argv);
$env = ocs_parse_env_file($root . '/.env');

if (($env['APP_ENV'] ?? 'local') !== 'local') {
    fwrite(STDERR, "FAIL seed-demo.php only runs with APP_ENV=local\n");
    exit(1);
}

$config = is_string($args['flags']['config'] ?? null) ? (string) $args['flags']['config'] : $root . '/build/www/config.php';
$db = ocs_connect($root, $config);
$m = new Context($db, DB_PREFIX, $root);

$set = static function (string $code, string $key, $value) use ($m): void {
    $m->setting->set($code, $key, $value);
    echo "  setting {$key}\n";
};

echo "modules on\n";
foreach ([
    'module_ocs_home_slider' => ['module_ocs_home_slider_status' => 1, 'module_ocs_home_slider_tiles' => 1, 'module_ocs_home_slider_menu' => 1],
    'module_ocs_benefits' => ['module_ocs_benefits_status' => 1],
    'module_ocs_brand_strip' => ['module_ocs_brand_strip_status' => 1],
    'module_ocs_quick_view' => ['module_ocs_quick_view_status' => 1],
    'module_ocs_stickers' => ['module_ocs_stickers_status' => 1],
    'module_ocs_newsletter' => ['module_ocs_newsletter_status' => 1],
] as $code => $settings) {
    foreach ($settings as $key => $value) {
        $set($code, $key, $value);
    }
}

echo "category icons\n";
$icons = ['20' => 'fa-desktop', '18' => 'fa-laptop', '25' => 'fa-puzzle-piece', '57' => 'fa-tablet', '17' => 'fa-cubes', '24' => 'fa-mobile', '33' => 'fa-camera', '34' => 'fa-music'];
foreach ($icons as $id => $icon) {
    if ($db->rows('SELECT category_id FROM ' . DB_PREFIX . 'category WHERE category_id = ' . (int) $id)) {
        $db->exec('INSERT INTO ' . DB_PREFIX . "ocs_menu_category (category_id, icon, popular) VALUES ({$id}, '" . $db->escape($icon) . "', " . (in_array($id, ['18', '24'], true) ? 1 : 0) . ') ON DUPLICATE KEY UPDATE icon = VALUES(icon), popular = VALUES(popular)');
        echo "  category {$id} {$icon}\n";
    }
}

echo "home slides\n";
if (!$db->rows('SELECT slide_id FROM ' . DB_PREFIX . 'ocs_slide LIMIT 1')) {
    $languages = $db->rows('SELECT language_id, code FROM ' . DB_PREFIX . 'language');
    $slides = [
        ['catalog/demo/banners/MacBookAir.jpg', '/index.php?route=product/special', ['uk-ua' => ['Акції тижня', 'Знижки до 30%'], 'en-gb' => ['Deals of the week', 'Up to 30% off']]],
        ['catalog/demo/banners/iPhone6.jpg', '/index.php?route=product/category&path=24', ['uk-ua' => ['Смартфони', 'Нові моделі'], 'en-gb' => ['Smartphones', 'New models']]],
        ['catalog/demo/banners/MacBookAir.jpg', '/index.php?route=product/category&path=18', ['uk-ua' => ['Ноутбуки', 'Знижки'], 'en-gb' => ['Laptops', 'Deals']]],
    ];
    foreach ($slides as $i => [$image, $link, $texts]) {
        $db->exec('INSERT INTO ' . DB_PREFIX . "ocs_slide (image, image_mobile, link, sort_order, status) VALUES ('" . $db->escape($image) . "', '', '" . $db->escape($link) . "', " . ($i + 1) . ', 1)');
        $id = $db->lastId();
        foreach ($languages as $language) {
            $t = $texts[$language['code']] ?? $texts['en-gb'];
            $db->exec('INSERT INTO ' . DB_PREFIX . "ocs_slide_description (slide_id, language_id, title, text) VALUES ({$id}, " . (int) $language['language_id'] . ", '" . $db->escape($t[0]) . "', '" . $db->escape($t[1]) . "')");
        }
    }
    echo "  3 slides\n";
}

echo "home layout (layout 1)\n";
$modules = [['ocs_home_slider', 'content_top', 1], ['ocs_benefits', 'content_top', 2], ['ocs_brand_strip', 'content_top', 4], ['ocs_newsletter', 'content_bottom', 5]];

foreach ($modules as [$code, $position, $sort]) {
    if (!$db->rows('SELECT layout_module_id FROM ' . DB_PREFIX . "layout_module WHERE layout_id = 1 AND code = '" . $db->escape($code) . "'")) {
        $db->exec('INSERT INTO ' . DB_PREFIX . "layout_module (layout_id, code, position, sort_order) VALUES (1, '" . $db->escape($code) . "', '{$position}', {$sort})");
        echo "  {$code}\n";
    }
}

echo "tabbed shelf\n";
if (!$db->rows('SELECT module_id FROM ' . DB_PREFIX . "module WHERE code = 'ocs_product_carousel'")) {
    $setting = json_encode([
        'name' => 'Best in categories', 'status' => '1', 'mode' => 'newest', 'category_id' => '0', 'ids' => '', 'limit' => '10', 'show_all' => '1',
        'tabs' => "Ноутбуки|18|bestsellers\nСмартфони|24|newest\nПланшети|57|newest",
        'title' => ['1' => 'Найкраще [[в категоріях]]', '2' => 'Best [[in categories]]'],
    ], JSON_UNESCAPED_UNICODE);
    $db->exec('INSERT INTO ' . DB_PREFIX . "module (name, code, setting) VALUES ('Best in categories', 'ocs_product_carousel', '" . $db->escape($setting) . "')");
    $id = $db->lastId();
    $db->exec('INSERT INTO ' . DB_PREFIX . "layout_module (layout_id, code, position, sort_order) VALUES (1, 'ocs_product_carousel.{$id}', 'content_top', 5)");
    echo "  module {$id}\n";
}

echo "done: run `php -d extension=mysqli scripts/refresh.php` and clear var/storage/cache if blocks look stale.\n";
