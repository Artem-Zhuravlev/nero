<?php
declare(strict_types=1);

// ocs_category_menu 001: register the module, permission, defaults with status 0 (REQ-EXT-031) (SPEC-027).
// Layout placement is done by the administrator (REQ-MNU-021). Rollback: set module_ocs_category_menu_status to 0.

return function (\Ocs\Migrate\Context $m): void {
    $m->extension->register('module', 'ocs_category_menu');
    $m->permission->grant(1, 'extension/module/ocs_category_menu');

    foreach (['module_ocs_category_menu_status' => 0, 'module_ocs_category_menu_levels' => 3] as $key => $value) {
        if ($m->setting->get('module_ocs_category_menu', $key) === null) {
            $m->setting->set('module_ocs_category_menu', $key, $value);
        }
    }
};
