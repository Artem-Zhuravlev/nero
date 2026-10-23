<?php
declare(strict_types=1);

// ocs_stickers 001: sticker tables, built-in stickers, module registration with status 0 (REQ-EXT-031), events (SPEC-029).
// Built-in stickers are seeded once (code column) and can be edited or switched off in the admin; re-running never overwrites them.
// Rollback: set module_ocs_stickers_status to 0; tables and data are kept (REQ-EXT-052).

return function (\Ocs\Migrate\Context $m): void {
    $m->createTable('ocs_sticker', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_sticker (
        sticker_id INT NOT NULL AUTO_INCREMENT,
        code VARCHAR(16) NOT NULL DEFAULT '',
        text_color VARCHAR(7) NOT NULL DEFAULT '#ffffff',
        bg_color VARCHAR(7) NOT NULL DEFAULT '#1d1d1f',
        position VARCHAR(2) NOT NULL DEFAULT 'tl',
        priority INT NOT NULL DEFAULT 0,
        rule_type VARCHAR(16) NOT NULL DEFAULT 'manual',
        rule_value INT NOT NULL DEFAULT 0,
        show_pct TINYINT NOT NULL DEFAULT 0,
        category_id INT NOT NULL DEFAULT 0,
        manufacturer_id INT NOT NULL DEFAULT 0,
        status TINYINT NOT NULL DEFAULT 1,
        PRIMARY KEY (sticker_id),
        KEY code (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->createTable('ocs_sticker_description', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_sticker_description (
        sticker_id INT NOT NULL,
        language_id INT NOT NULL,
        name VARCHAR(32) NOT NULL DEFAULT '',
        PRIMARY KEY (sticker_id, language_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->createTable('ocs_sticker_product', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_sticker_product (
        sticker_id INT NOT NULL,
        product_id INT NOT NULL,
        PRIMARY KEY (sticker_id, product_id),
        KEY product_id (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    // built-in stickers: code => [position, fg, bg, priority, rule, value, show_pct, names]
    $builtin = [
        'new' => ['tr', '#ffffff', '#0071e3', 40, 'new', 30, 0, ['uk-ua' => 'Новинка', 'en-gb' => 'New', 'ru-ru' => 'Новинка']],
        'hit' => ['tr', '#ffffff', '#bf4800', 50, 'manual', 0, 0, ['uk-ua' => 'Хіт', 'en-gb' => 'Hit', 'ru-ru' => 'Хит']],
        'sale' => ['tl', '#ffffff', '#d70015', 60, 'discount', 1, 1, ['uk-ua' => 'Акція', 'en-gb' => 'Sale', 'ru-ru' => 'Акция']],
        'top' => ['tr', '#ffffff', '#248a3d', 30, 'top_sales', 10, 0, ['uk-ua' => 'Топ продажів', 'en-gb' => 'Top seller', 'ru-ru' => 'Топ продаж']],
        'preorder' => ['bl', '#1d1d1f', '#ffd60a', 20, 'manual', 0, 0, ['uk-ua' => 'Передзамовлення', 'en-gb' => 'Pre-order', 'ru-ru' => 'Предзаказ']],
        'out' => ['bl', '#ffffff', '#6e6e73', 10, 'out_of_stock', 0, 0, ['uk-ua' => 'Немає в наявності', 'en-gb' => 'Out of stock', 'ru-ru' => 'Нет в наличии']],
    ];

    $languages = $m->rows('SELECT language_id, code FROM {{PREFIX}}language');

    foreach ($builtin as $code => [$position, $fg, $bg, $priority, $rule, $value, $pct, $names]) {
        if ($m->rows('SELECT sticker_id FROM {{PREFIX}}ocs_sticker WHERE code = ?', [$code])) {
            continue;
        }

        $m->sql('INSERT INTO {{PREFIX}}ocs_sticker (code, text_color, bg_color, position, priority, rule_type, rule_value, show_pct, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)', [$code, $fg, $bg, $position, $priority, $rule, $value, $pct]);

        $id = (int) $m->rows('SELECT sticker_id FROM {{PREFIX}}ocs_sticker WHERE code = ?', [$code])[0]['sticker_id'];

        foreach ($languages as $language) {
            $name = $names[$language['code']] ?? $names['en-gb'];
            $m->sql('INSERT INTO {{PREFIX}}ocs_sticker_description (sticker_id, language_id, name) VALUES (?, ?, ?)', [$id, (int) $language['language_id'], $name]);
        }
    }

    $m->extension->register('module', 'ocs_stickers');
    $m->permission->grant(1, 'extension/module/ocs_stickers');

    foreach (['module_ocs_stickers_status' => 0, 'module_ocs_stickers_limit' => 2] as $key => $value) {
        if ($m->setting->get('module_ocs_stickers', $key) === null) {
            $m->setting->set('module_ocs_stickers', $key, $value);
        }
    }

    $events = [
        'ocs_stickers_product_before' => ['catalog/view/product/product/before', 'extension/ocs_stickers/events/beforeProduct'],
        'ocs_stickers_category_before' => ['catalog/view/product/category/before', 'extension/ocs_stickers/events/beforeCategory'],
        'ocs_stickers_search_before' => ['catalog/view/product/search/before', 'extension/ocs_stickers/events/beforeSearch'],
        'ocs_stickers_manufacturer_before' => ['catalog/view/product/manufacturer_info/before', 'extension/ocs_stickers/events/beforeManufacturer'],
        'ocs_stickers_special_before' => ['catalog/view/product/special/before', 'extension/ocs_stickers/events/beforeSpecial'],
        'ocs_stickers_form_before' => ['admin/view/catalog/product_form/before', 'extension/ocs_stickers/events/beforeProductForm'],
        'ocs_stickers_add_after' => ['admin/model/catalog/product/addProduct/after', 'extension/ocs_stickers/events/afterAddProduct'],
        'ocs_stickers_edit_after' => ['admin/model/catalog/product/editProduct/after', 'extension/ocs_stickers/events/afterEditProduct'],
    ];
    foreach ($events as $code => [$trigger, $action]) {
        $m->event->register($code, $trigger, $action, 4);
    }

    $m->ocmod->install('custom/ocmod/ocs_stickers_product_form.ocmod.xml');
};
