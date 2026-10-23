<?php
declare(strict_types=1);

// ocs_viewed 001: account history table, module registration with status 0 (REQ-EXT-031), recording event (SPEC-030).
// The history is functional (first-party cookie "ocs_viewed" + this table); see the spec's note on consent (SPEC-017).
// Rollback: set module_ocs_viewed_status to 0; the table and cookies are left alone (REQ-EXT-052).

return function (\Ocs\Migrate\Context $m): void {
    $m->createTable('ocs_viewed', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_viewed (
        customer_id INT NOT NULL,
        product_id INT NOT NULL,
        viewed_at DATETIME NOT NULL,
        PRIMARY KEY (customer_id, product_id),
        KEY customer_time (customer_id, viewed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->extension->register('module', 'ocs_viewed');
    $m->permission->grant(1, 'extension/module/ocs_viewed');

    foreach (['module_ocs_viewed_status' => 0, 'module_ocs_viewed_limit' => 4] as $key => $value) {
        if ($m->setting->get('module_ocs_viewed', $key) === null) {
            $m->setting->set('module_ocs_viewed', $key, $value);
        }
    }

    $m->event->register('ocs_viewed_record', 'catalog/controller/product/product/before', 'extension/ocs_viewed/events/beforeProduct', 1);
};
