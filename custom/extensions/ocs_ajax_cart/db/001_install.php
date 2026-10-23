<?php
declare(strict_types=1);

// ocs_ajax_cart 001: register the module, permission, defaults with status 0 (REQ-EXT-031), view events (SPEC-026).
// Event sort order 3 runs after ocs_listing (1) and ocs_quick_order (2): the availability label reads the stock quantity
// that the ocs_listing event adds to the product rows.
// Rollback: set module_ocs_ajax_cart_status to 0; the core cart dropdown and alerts work again.

return function (\Ocs\Migrate\Context $m): void {
    $m->extension->register('module', 'ocs_ajax_cart');
    $m->permission->grant(1, 'extension/module/ocs_ajax_cart');

    foreach (['module_ocs_ajax_cart_status' => 0, 'module_ocs_ajax_cart_low_stock' => 5] as $key => $value) {
        if ($m->setting->get('module_ocs_ajax_cart', $key) === null) {
            $m->setting->set('module_ocs_ajax_cart', $key, $value);
        }
    }

    $events = [
        'ocs_ajax_cart_header_before' => ['catalog/view/common/header/before', 'beforeHeader'],
        'ocs_ajax_cart_product_before' => ['catalog/view/product/product/before', 'beforeProduct'],
        'ocs_ajax_cart_category_before' => ['catalog/view/product/category/before', 'beforeCategory'],
        'ocs_ajax_cart_search_before' => ['catalog/view/product/search/before', 'beforeSearch'],
        'ocs_ajax_cart_manufacturer_before' => ['catalog/view/product/manufacturer_info/before', 'beforeManufacturer'],
        'ocs_ajax_cart_special_before' => ['catalog/view/product/special/before', 'beforeSpecial'],
    ];
    foreach ($events as $code => [$trigger, $method]) {
        $m->event->register($code, $trigger, 'extension/ocs_ajax_cart/events/' . $method, 3);
    }
};
