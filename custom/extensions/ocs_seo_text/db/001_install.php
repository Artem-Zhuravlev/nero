<?php
declare(strict_types=1);

// ocs_seo_text 001: register the module, permission, defaults with status 0 (REQ-EXT-031) (SPEC-033 REQ-HOM-040).
// Rollback: set module_ocs_seo_text_status to 0.

return function (\Ocs\Migrate\Context $m): void {
    $m->extension->register('module', 'ocs_seo_text');
    $m->permission->grant(1, 'extension/module/ocs_seo_text');

    if ($m->setting->get('module_ocs_seo_text', 'module_ocs_seo_text_status') === null) {
        $m->setting->set('module_ocs_seo_text', 'module_ocs_seo_text_status', 0);
    }
    if ($m->setting->get('module_ocs_seo_text', 'module_ocs_seo_text_l') === null) {
        $m->setting->set('module_ocs_seo_text', 'module_ocs_seo_text_l', []);
    }
};
