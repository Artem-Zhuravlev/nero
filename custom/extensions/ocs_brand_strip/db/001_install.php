<?php
declare(strict_types=1);

// ocs_brand_strip 001: register the module, permission, defaults with status 0 (REQ-EXT-031) (SPEC-033 REQ-HOM-010).
// Rollback: set module_ocs_brand_strip_status to 0.

return function (\Ocs\Migrate\Context $m): void {
    $m->extension->register('module', 'ocs_brand_strip');
    $m->permission->grant(1, 'extension/module/ocs_brand_strip');

    $defaults = [
        'module_ocs_brand_strip_status' => 0,
        'module_ocs_brand_strip_source' => 'all',
        'module_ocs_brand_strip_selected' => '',
        'module_ocs_brand_strip_sort' => 'name',
        'module_ocs_brand_strip_limit' => 20,
        'module_ocs_brand_strip_width' => 140,
        'module_ocs_brand_strip_height' => 80,
    ];
    foreach ($defaults as $key => $value) {
        if ($m->setting->get('module_ocs_brand_strip', $key) === null) {
            $m->setting->set('module_ocs_brand_strip', $key, $value);
        }
    }
};
