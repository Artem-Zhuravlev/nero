<?php
declare(strict_types=1);

// ocs_benefits 001: register the module, permission, defaults with status 0 (REQ-EXT-031) (SPEC-038 REQ-LAY-006).
// Rollback: set module_ocs_benefits_status to 0.

return function (\Ocs\Migrate\Context $m): void {
    $m->extension->register('module', 'ocs_benefits');
    $m->permission->grant(1, 'extension/module/ocs_benefits');

    foreach (['module_ocs_benefits_status' => 0, 'module_ocs_benefits_items' => []] as $key => $value) {
        if ($m->setting->get('module_ocs_benefits', $key) === null) {
            $m->setting->set('module_ocs_benefits', $key, $value);
        }
    }
};
