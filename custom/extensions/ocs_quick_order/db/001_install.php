<?php
declare(strict_types=1);

// ocs_quick_order 001: register the module, permission, defaults with status 0 (REQ-EXT-031), view events (SPEC-025).
// Rollback: set module_ocs_quick_order_status to 0; buttons and endpoints become inactive.

return function (\Ocs\Migrate\Context $m): void {
    $m->extension->register('module', 'ocs_quick_order');
    $m->permission->grant(1, 'extension/module/ocs_quick_order');

    $defaults = [
        'module_ocs_quick_order_status' => 0,
        'module_ocs_quick_order_name' => 'required',
        'module_ocs_quick_order_phone' => 'required',
        'module_ocs_quick_order_email' => 'off',
        'module_ocs_quick_order_comment' => 'optional',
        'module_ocs_quick_order_order_status_id' => 1,
    ];
    foreach ($defaults as $key => $value) {
        if ($m->setting->get('module_ocs_quick_order', $key) === null) {
            $m->setting->set('module_ocs_quick_order', $key, $value);
        }
    }

    $events = [
        'ocs_quick_order_product_before' => ['catalog/view/product/product/before', 'beforeProduct'],
        'ocs_quick_order_category_before' => ['catalog/view/product/category/before', 'beforeCategory'],
        'ocs_quick_order_search_before' => ['catalog/view/product/search/before', 'beforeSearch'],
        'ocs_quick_order_manufacturer_before' => ['catalog/view/product/manufacturer_info/before', 'beforeManufacturer'],
        'ocs_quick_order_special_before' => ['catalog/view/product/special/before', 'beforeSpecial'],
    ];
    foreach ($events as $code => [$trigger, $method]) {
        $m->event->register($code, $trigger, 'extension/ocs_quick_order/events/' . $method, 2);
    }
};
