<?php
declare(strict_types=1);

// ocs_preorder 001: pre-order requests table, module registration, defaults with status 0 (REQ-EXT-031), view event (SPEC-025).
// Rollback: set module_ocs_preorder_status to 0; the table and its data are kept (REQ-EXT-052).

return function (\Ocs\Migrate\Context $m): void {
    $m->createTable('ocs_preorder', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_preorder (
        preorder_id INT NOT NULL AUTO_INCREMENT,
        product_id INT NOT NULL,
        product_name VARCHAR(255) NOT NULL DEFAULT '',
        quantity INT NOT NULL DEFAULT 1,
        options TEXT NOT NULL,
        name VARCHAR(64) NOT NULL DEFAULT '',
        phone VARCHAR(32) NOT NULL DEFAULT '',
        email VARCHAR(96) NOT NULL DEFAULT '',
        comment TEXT NOT NULL,
        expected_date VARCHAR(10) NOT NULL DEFAULT '',
        order_id INT NOT NULL DEFAULT 0,
        status TINYINT NOT NULL DEFAULT 0,
        date_added DATETIME NOT NULL,
        PRIMARY KEY (preorder_id),
        KEY product_id (product_id),
        KEY status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->extension->register('module', 'ocs_preorder');
    $m->permission->grant(1, 'extension/module/ocs_preorder');

    $defaults = [
        'module_ocs_preorder_status' => 0,
        'module_ocs_preorder_global' => 1,
        'module_ocs_preorder_name' => 'required',
        'module_ocs_preorder_phone' => 'required',
        'module_ocs_preorder_email' => 'optional',
        'module_ocs_preorder_comment' => 'optional',
        'module_ocs_preorder_notify_admin' => 1,
        'module_ocs_preorder_create_order' => 0,
        'module_ocs_preorder_order_status_id' => 1,
        'module_ocs_preorder_overrides' => '',
    ];
    foreach ($defaults as $key => $value) {
        if ($m->setting->get('module_ocs_preorder', $key) === null) {
            $m->setting->set('module_ocs_preorder', $key, $value);
        }
    }
    if ($m->setting->get('module_ocs_preorder', 'module_ocs_preorder_l') === null) {
        $m->setting->set('module_ocs_preorder', 'module_ocs_preorder_l', []);
    }

    $m->event->register('ocs_preorder_product_before', 'catalog/view/product/product/before', 'extension/ocs_preorder/events/beforeProduct', 1);
};
