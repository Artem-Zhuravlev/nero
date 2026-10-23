<?php
declare(strict_types=1);

// ocs_mega_menu 001: register the module, permission, defaults (status 0, REQ-EXT-031), view event for common/menu (SPEC-027).
// Rollback: set module_ocs_mega_menu_status to 0; the theme falls back to the core category menu.

return function (\Ocs\Migrate\Context $m): void {
    $m->extension->register('module', 'ocs_mega_menu');
    $m->permission->grant(1, 'extension/module/ocs_mega_menu');

    $defaults = [
        'module_ocs_mega_menu_status' => 0,
        'module_ocs_mega_menu_levels' => 3,
        'module_ocs_mega_menu_columns' => 3,
        'module_ocs_mega_menu_behaviour' => 'hover',
    ];
    foreach ($defaults as $key => $value) {
        if ($m->setting->get('module_ocs_mega_menu', $key) === null) {
            $m->setting->set('module_ocs_mega_menu', $key, $value);
        }
    }

    $m->event->register('ocs_mega_menu_menu_before', 'catalog/view/common/menu/before', 'extension/ocs_mega_menu/events/beforeMenu', 1);
};
