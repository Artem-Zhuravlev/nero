<?php
declare(strict_types=1);

// ocs_category_wall 001: register the module, permission, defaults with status 0 (REQ-EXT-031) (SPEC-027).
// Rollback: set module_ocs_category_wall_status to 0.

return function (\Ocs\Migrate\Context $m): void {
    $m->extension->register('module', 'ocs_category_wall');
    $m->permission->grant(1, 'extension/module/ocs_category_wall');

    $defaults = [
        'module_ocs_category_wall_status' => 0,
        'module_ocs_category_wall_cols_desktop' => 4,
        'module_ocs_category_wall_cols_tablet' => 3,
        'module_ocs_category_wall_cols_mobile' => 2,
        'module_ocs_category_wall_sort' => 'sort',
        'module_ocs_category_wall_source' => 'top',
        'module_ocs_category_wall_selected' => '',
        'module_ocs_category_wall_show_children' => 1,
        'module_ocs_category_wall_children_limit' => 5,
        'module_ocs_category_wall_image_width' => 200,
        'module_ocs_category_wall_image_height' => 200,
    ];
    foreach ($defaults as $key => $value) {
        if ($m->setting->get('module_ocs_category_wall', $key) === null) {
            $m->setting->set('module_ocs_category_wall', $key, $value);
        }
    }
};
