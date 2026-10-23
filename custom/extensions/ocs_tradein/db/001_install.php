<?php
declare(strict_types=1);

// ocs_tradein 001: lead table, settings (status 0), banner events (SPEC-036).
// Rollback: set module_ocs_tradein_status to 0; leads are kept (REQ-EXT-052).

return function (\Ocs\Migrate\Context $m): void {
    $m->createTable('ocs_tradein_lead', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_tradein_lead (
        lead_id INT NOT NULL AUTO_INCREMENT,
        product_id INT NOT NULL DEFAULT 0,
        language_id INT NOT NULL DEFAULT 0,
        name VARCHAR(64) NOT NULL DEFAULT '',
        phone VARCHAR(32) NOT NULL DEFAULT '',
        email VARCHAR(96) NOT NULL DEFAULT '',
        device TEXT NOT NULL,
        photos VARCHAR(255) NOT NULL DEFAULT '',
        status TINYINT NOT NULL DEFAULT 0,
        ip VARCHAR(40) NOT NULL DEFAULT '',
        date_added DATETIME NOT NULL,
        PRIMARY KEY (lead_id),
        KEY status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->extension->register('module', 'ocs_tradein');
    $m->permission->grant(1, 'extension/module/ocs_tradein');

    $defaults = [
        'module_ocs_tradein_status' => 0,
        'module_ocs_tradein_categories' => '',
        'module_ocs_tradein_email' => '',
        'module_ocs_tradein_l' => [],
    ];
    foreach ($defaults as $key => $value) {
        if ($m->setting->get('module_ocs_tradein', $key) === null) {
            $m->setting->set('module_ocs_tradein', $key, $value);
        }
    }

    $events = [
        'ocs_tradein_product' => ['catalog/view/product/product/before', 'extension/ocs_tradein/events/beforeProduct'],
        'ocs_tradein_category' => ['catalog/view/product/category/before', 'extension/ocs_tradein/events/beforeCategory'],
    ];
    foreach ($events as $code => [$trigger, $action]) {
        $m->event->register($code, $trigger, $action, 8);
    }
};
