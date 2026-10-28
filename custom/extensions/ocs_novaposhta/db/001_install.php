<?php
declare(strict_types=1);

// ocs_novaposhta 001: Nova Poshta directories (areas, cities, warehouses), sync cursor, order point, shipping extension, events (SPEC-085).
// Rollback: set shipping_ocs_novaposhta_status to 0; the tables are kept (REQ-EXT-052).

return function (\Ocs\Migrate\Context $m): void {
    $m->createTable('ocs_np_area', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_np_area (
        ref CHAR(36) NOT NULL,
        name_uk VARCHAR(128) NOT NULL DEFAULT '',
        name_ru VARCHAR(128) NOT NULL DEFAULT '',
        is_active TINYINT NOT NULL DEFAULT 1,
        run_id INT NOT NULL DEFAULT 0,
        hash CHAR(32) NOT NULL DEFAULT '',
        PRIMARY KEY (ref)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->createTable('ocs_np_city', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_np_city (
        ref CHAR(36) NOT NULL,
        area_ref CHAR(36) NOT NULL DEFAULT '',
        name_uk VARCHAR(128) NOT NULL DEFAULT '',
        name_ru VARCHAR(128) NOT NULL DEFAULT '',
        search_uk VARCHAR(128) NOT NULL DEFAULT '',
        search_ru VARCHAR(128) NOT NULL DEFAULT '',
        type_uk VARCHAR(64) NOT NULL DEFAULT '',
        type_ru VARCHAR(64) NOT NULL DEFAULT '',
        warehouses_count INT NOT NULL DEFAULT 0,
        is_active TINYINT NOT NULL DEFAULT 1,
        run_id INT NOT NULL DEFAULT 0,
        hash CHAR(32) NOT NULL DEFAULT '',
        PRIMARY KEY (ref),
        KEY search_uk (search_uk),
        KEY search_ru (search_ru),
        KEY area_ref (area_ref)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->createTable('ocs_np_warehouse', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_np_warehouse (
        ref CHAR(36) NOT NULL,
        city_ref CHAR(36) NOT NULL DEFAULT '',
        number INT NOT NULL DEFAULT 0,
        type VARCHAR(10) NOT NULL DEFAULT 'branch',
        name_uk VARCHAR(255) NOT NULL DEFAULT '',
        name_ru VARCHAR(255) NOT NULL DEFAULT '',
        search VARCHAR(600) NOT NULL DEFAULT '',
        schedule TEXT NOT NULL,
        max_weight INT NOT NULL DEFAULT 0,
        lat VARCHAR(20) NOT NULL DEFAULT '',
        lng VARCHAR(20) NOT NULL DEFAULT '',
        is_active TINYINT NOT NULL DEFAULT 1,
        run_id INT NOT NULL DEFAULT 0,
        hash CHAR(32) NOT NULL DEFAULT '',
        PRIMARY KEY (ref),
        KEY city_type_number (city_ref, is_active, type, number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->createTable('ocs_np_sync', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_np_sync (
        entity VARCHAR(16) NOT NULL,
        run_id INT NOT NULL DEFAULT 0,
        page INT NOT NULL DEFAULT 1,
        status VARCHAR(10) NOT NULL DEFAULT 'idle',
        started_at INT NOT NULL DEFAULT 0,
        finished_at INT NOT NULL DEFAULT 0,
        rows_run INT NOT NULL DEFAULT 0,
        rows_total INT NOT NULL DEFAULT 0,
        error VARCHAR(255) NOT NULL DEFAULT '',
        PRIMARY KEY (entity)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->createTable('ocs_np_order', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_np_order (
        order_id INT NOT NULL,
        city_ref CHAR(36) NOT NULL DEFAULT '',
        warehouse_ref CHAR(36) NOT NULL DEFAULT '',
        type VARCHAR(10) NOT NULL DEFAULT '',
        date_added DATETIME NOT NULL,
        PRIMARY KEY (order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->extension->register('shipping', 'ocs_novaposhta');
    $m->permission->grant(1, 'extension/shipping/ocs_novaposhta');

    $defaults = [
        'shipping_ocs_novaposhta_status' => 0,
        'shipping_ocs_novaposhta_sort_order' => 1,
        'shipping_ocs_novaposhta_api_key' => '',
        'shipping_ocs_novaposhta_cron_token' => bin2hex(random_bytes(16)),
        'shipping_ocs_novaposhta_cost' => '70',
        'shipping_ocs_novaposhta_free_total' => '0',
        'shipping_ocs_novaposhta_tax_class_id' => 0,
        'shipping_ocs_novaposhta_geo_zone_id' => 0,
        'shipping_ocs_novaposhta_only_ua' => 1,
        'shipping_ocs_novaposhta_require_point' => 1,
        'shipping_ocs_novaposhta_preset' => 'default',
        'shipping_ocs_novaposhta_batch_size' => 500,
        'shipping_ocs_novaposhta_pause_ms' => 200,
    ];
    foreach ($defaults as $key => $value) {
        if ($m->setting->get('shipping_ocs_novaposhta', $key) === null) {
            $m->setting->set('shipping_ocs_novaposhta', $key, $value);
        }
    }
    foreach (['shipping_ocs_novaposhta_title', 'shipping_ocs_novaposhta_selectors'] as $key) {
        if ($m->setting->get('shipping_ocs_novaposhta', $key) === null) {
            $m->setting->set('shipping_ocs_novaposhta', $key, []);
        }
    }

    $events = [
        'ocs_np_header_before' => ['catalog/view/common/header/before', 'extension/ocs_novaposhta/events/beforeHeader'],
        'ocs_np_add_order_before' => ['catalog/model/checkout/order/addOrder/before', 'extension/ocs_novaposhta/events/beforeAddOrder'],
        'ocs_np_add_order_after' => ['catalog/model/checkout/order/addOrder/after', 'extension/ocs_novaposhta/events/afterAddOrder'],
        'ocs_np_edit_order_before' => ['catalog/model/checkout/order/editOrder/before', 'extension/ocs_novaposhta/events/beforeEditOrder'],
        'ocs_np_edit_order_after' => ['catalog/model/checkout/order/editOrder/after', 'extension/ocs_novaposhta/events/afterEditOrder'],
    ];
    foreach ($events as $code => [$trigger, $action]) {
        $m->event->register($code, $trigger, $action, 5);
    }
};
