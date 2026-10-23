<?php
declare(strict_types=1);

// ocs_back_in_stock 001: subscriptions table, module registration, defaults with status 0 (REQ-EXT-031), events (SPEC-025).
// Rollback: set module_ocs_back_in_stock_status to 0; data is kept (REQ-EXT-052).

return function (\Ocs\Migrate\Context $m): void {
    $m->createTable('ocs_back_in_stock', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_back_in_stock (
        subscription_id INT NOT NULL AUTO_INCREMENT,
        product_id INT NOT NULL,
        product_name VARCHAR(255) NOT NULL DEFAULT '',
        option_key VARCHAR(64) NOT NULL DEFAULT '',
        email VARCHAR(96) NOT NULL,
        language_code VARCHAR(10) NOT NULL DEFAULT '',
        status TINYINT NOT NULL DEFAULT 0,
        date_added DATETIME NOT NULL,
        date_notified DATETIME NULL DEFAULT NULL,
        PRIMARY KEY (subscription_id),
        UNIQUE KEY subscription (product_id, option_key, email),
        KEY status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->extension->register('module', 'ocs_back_in_stock');
    $m->permission->grant(1, 'extension/module/ocs_back_in_stock');

    if ($m->setting->get('module_ocs_back_in_stock', 'module_ocs_back_in_stock_status') === null) {
        $m->setting->set('module_ocs_back_in_stock', 'module_ocs_back_in_stock_status', 0);
    }

    $m->event->register('ocs_back_in_stock_product_before', 'catalog/view/product/product/before', 'extension/ocs_back_in_stock/events/beforeProduct', 1);
    $m->event->register('ocs_back_in_stock_product_edit_after', 'admin/model/catalog/product/editProduct/after', 'extension/ocs_back_in_stock/events/afterEditProduct', 1);
};
