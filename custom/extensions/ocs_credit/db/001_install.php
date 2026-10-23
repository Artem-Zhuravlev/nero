<?php
declare(strict_types=1);

// ocs_credit 001: register the module, permission, defaults with status 0 (REQ-EXT-031), product view event (SPEC-036).
// Rollback: set module_ocs_credit_status to 0; the block disappears.

return function (\Ocs\Migrate\Context $m): void {
    $m->extension->register('module', 'ocs_credit');
    $m->permission->grant(1, 'extension/module/ocs_credit');

    $defaults = [
        'module_ocs_credit_status' => 0,
        'module_ocs_credit_plans' => [],
        'module_ocs_credit_l' => [],
    ];
    foreach ($defaults as $key => $value) {
        if ($m->setting->get('module_ocs_credit', $key) === null) {
            $m->setting->set('module_ocs_credit', $key, $value);
        }
    }

    $m->event->register('ocs_credit_product', 'catalog/view/product/product/before', 'extension/ocs_credit/events/beforeProduct', 7);
};
