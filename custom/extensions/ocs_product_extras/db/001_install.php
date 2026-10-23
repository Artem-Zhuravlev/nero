<?php
declare(strict_types=1);

// ocs_product_extras 001: question and accessory tables, settings (status 0), events and the product form OCMOD (SPEC-035).
// Rollback: set module_ocs_product_extras_status to 0; data is kept (REQ-EXT-052).

return function (\Ocs\Migrate\Context $m): void {
    $m->createTable('ocs_question', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_question (
        question_id INT NOT NULL AUTO_INCREMENT,
        product_id INT NOT NULL,
        customer_id INT NOT NULL DEFAULT 0,
        name VARCHAR(64) NOT NULL,
        email VARCHAR(96) NOT NULL DEFAULT '',
        text TEXT NOT NULL,
        answer TEXT NOT NULL,
        status TINYINT NOT NULL DEFAULT 0,
        ip VARCHAR(40) NOT NULL DEFAULT '',
        date_added DATETIME NOT NULL,
        PRIMARY KEY (question_id),
        KEY product_status (product_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->createTable('ocs_product_accessory', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_product_accessory (
        product_id INT NOT NULL,
        accessory_id INT NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        PRIMARY KEY (product_id, accessory_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->extension->register('module', 'ocs_product_extras');
    $m->permission->grant(1, 'extension/module/ocs_product_extras');

    $defaults = [
        'module_ocs_product_extras_status' => 0,
        'module_ocs_product_extras_key_attrs' => '',
        'module_ocs_product_extras_questions' => 1,
        'module_ocs_product_extras_accessories' => 1,
        'module_ocs_product_extras_similar' => 1,
        'module_ocs_product_extras_similar_limit' => 10,
    ];
    foreach ($defaults as $key => $value) {
        if ($m->setting->get('module_ocs_product_extras', $key) === null) {
            $m->setting->set('module_ocs_product_extras', $key, $value);
        }
    }

    $events = [
        'ocs_product_extras_view' => ['catalog/view/product/product/before', 'extension/ocs_product_extras/events/beforeProduct'],
        'ocs_product_extras_form' => ['admin/view/catalog/product_form/before', 'extension/ocs_product_extras/events/beforeProductForm'],
        'ocs_product_extras_add' => ['admin/model/catalog/product/addProduct/after', 'extension/ocs_product_extras/events/afterAddProduct'],
        'ocs_product_extras_edit' => ['admin/model/catalog/product/editProduct/after', 'extension/ocs_product_extras/events/afterEditProduct'],
        'ocs_product_extras_delete' => ['admin/model/catalog/product/deleteProduct/before', 'extension/ocs_product_extras/events/beforeDeleteProduct'],
    ];
    foreach ($events as $code => [$trigger, $action]) {
        $m->event->register($code, $trigger, $action, 5);
    }

    $m->ocmod->install('custom/ocmod/ocs_product_extras_form.ocmod.xml');
};
