<?php
declare(strict_types=1);

// Migration 0008: the cities feature is removed (SPEC-052 REQ-RCT-003, 004). Unregisters the module, events, permission and
// settings, drops the city/store tables. Idempotent; the data is not recoverable (restore the SPEC-032 commit to bring the feature back).

return function (\Ocs\Migrate\Context $m): void {
    $m->event->unregisterByCode('ocs_city_header');
    $m->event->unregisterByCode('ocs_city_product');
    $m->extension->unregister('module', 'ocs_city');
    $m->permission->revoke(1, 'extension/module/ocs_city');
    $m->setting->delete('module_ocs_city', 'module_ocs_city_status', 'module_ocs_city_tracking', 'module_ocs_city_pickup_minutes', 'module_ocs_city_l');

    foreach (['ocs_store_stock', 'ocs_store_description', 'ocs_store', 'ocs_city_delivery_description', 'ocs_city_delivery', 'ocs_city_description', 'ocs_city'] as $table) {
        $m->sql('DROP TABLE IF EXISTS {{PREFIX}}' . $table);
    }
};
