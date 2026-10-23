<?php
declare(strict_types=1);

// ocs_city 001: cities, delivery rules, stores, optional store stock, settings (status 0) and storefront events (SPEC-032).
// Rollback: set module_ocs_city_status to 0; data is kept (REQ-EXT-052).

return function (\Ocs\Migrate\Context $m): void {
    $m->createTable('ocs_city', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_city (
        city_id INT NOT NULL AUTO_INCREMENT,
        sort_order INT NOT NULL DEFAULT 0,
        is_default TINYINT NOT NULL DEFAULT 0,
        status TINYINT NOT NULL DEFAULT 1,
        PRIMARY KEY (city_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->createTable('ocs_city_description', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_city_description (
        city_id INT NOT NULL,
        language_id INT NOT NULL,
        name VARCHAR(128) NOT NULL DEFAULT '',
        PRIMARY KEY (city_id, language_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->createTable('ocs_city_delivery', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_city_delivery (
        city_id INT NOT NULL,
        method VARCHAR(16) NOT NULL,
        price DECIMAL(15,4) NOT NULL DEFAULT 0,
        free_from DECIMAL(15,4) NOT NULL DEFAULT 0,
        days_min INT NOT NULL DEFAULT 0,
        days_max INT NOT NULL DEFAULT 0,
        cutoff VARCHAR(5) NOT NULL DEFAULT '',
        status TINYINT NOT NULL DEFAULT 1,
        PRIMARY KEY (city_id, method)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->createTable('ocs_city_delivery_description', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_city_delivery_description (
        city_id INT NOT NULL,
        method VARCHAR(16) NOT NULL,
        language_id INT NOT NULL,
        note VARCHAR(255) NOT NULL DEFAULT '',
        PRIMARY KEY (city_id, method, language_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->createTable('ocs_store', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_store (
        store_id INT NOT NULL AUTO_INCREMENT,
        city_id INT NOT NULL,
        phone VARCHAR(32) NOT NULL DEFAULT '',
        map_link VARCHAR(255) NOT NULL DEFAULT '',
        latitude VARCHAR(16) NOT NULL DEFAULT '',
        longitude VARCHAR(16) NOT NULL DEFAULT '',
        sort_order INT NOT NULL DEFAULT 0,
        status TINYINT NOT NULL DEFAULT 1,
        PRIMARY KEY (store_id),
        KEY city_id (city_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->createTable('ocs_store_description', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_store_description (
        store_id INT NOT NULL,
        language_id INT NOT NULL,
        name VARCHAR(128) NOT NULL DEFAULT '',
        address VARCHAR(255) NOT NULL DEFAULT '',
        hours VARCHAR(255) NOT NULL DEFAULT '',
        PRIMARY KEY (store_id, language_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    // optional: filled by an import / SPEC-012 data; without rows the block falls back to the product quantity
    $m->createTable('ocs_store_stock', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_store_stock (
        store_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 0,
        PRIMARY KEY (store_id, product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->extension->register('module', 'ocs_city');
    $m->permission->grant(1, 'extension/module/ocs_city');

    $defaults = [
        'module_ocs_city_status' => 0,
        'module_ocs_city_tracking' => 1,
        'module_ocs_city_pickup_minutes' => 0,
        'module_ocs_city_l' => [],
    ];
    foreach ($defaults as $key => $value) {
        if ($m->setting->get('module_ocs_city', $key) === null) {
            $m->setting->set('module_ocs_city', $key, $value);
        }
    }

    $events = [
        'ocs_city_header' => ['catalog/view/common/header/before', 'extension/ocs_city/events/beforeHeader'],
        'ocs_city_product' => ['catalog/view/product/product/before', 'extension/ocs_city/events/beforeProduct'],
    ];
    foreach ($events as $code => [$trigger, $action]) {
        $m->event->register($code, $trigger, $action, 6);
    }
};
