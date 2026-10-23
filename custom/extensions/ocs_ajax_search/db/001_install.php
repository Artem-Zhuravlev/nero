<?php
declare(strict_types=1);

// ocs_ajax_search 001: register the module, permission, defaults with status 0 (REQ-EXT-031), view event for the search box (SPEC-028).
// No index is added on oc_product(model, sku): core tables are not altered by migrations (REQ-DB-030); the model/SKU lookup is a prefix
// match limited to a few rows (the endpoint is rate limited and the product part uses the core filter).
// Rollback: set module_ocs_ajax_search_status to 0; the core search box works again.

return function (\Ocs\Migrate\Context $m): void {
    $m->extension->register('module', 'ocs_ajax_search');
    $m->permission->grant(1, 'extension/module/ocs_ajax_search');

    $defaults = [
        'module_ocs_ajax_search_status' => 0,
        'module_ocs_ajax_search_limit' => 6,
        'module_ocs_ajax_search_min_length' => 2,
        'module_ocs_ajax_search_categories' => 1,
        'module_ocs_ajax_search_brands' => 1,
    ];
    foreach ($defaults as $key => $value) {
        if ($m->setting->get('module_ocs_ajax_search', $key) === null) {
            $m->setting->set('module_ocs_ajax_search', $key, $value);
        }
    }
    if ($m->setting->get('module_ocs_ajax_search', 'module_ocs_ajax_search_l') === null) {
        $m->setting->set('module_ocs_ajax_search', 'module_ocs_ajax_search_l', []);
    }

    $m->event->register('ocs_ajax_search_box_before', 'catalog/view/common/search/before', 'extension/ocs_ajax_search/events/beforeSearch', 1);
};
