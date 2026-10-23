<?php
declare(strict_types=1);

// ocs_quick_view 001: register the module, permission, defaults with status 0 (REQ-EXT-031), header event (SPEC-037).
// Rollback: set module_ocs_quick_view_status to 0; the card buttons disappear.

return function (\Ocs\Migrate\Context $m): void {
    $m->extension->register('module', 'ocs_quick_view');
    $m->permission->grant(1, 'extension/module/ocs_quick_view');

    if ($m->setting->get('module_ocs_quick_view', 'module_ocs_quick_view_status') === null) {
        $m->setting->set('module_ocs_quick_view', 'module_ocs_quick_view_status', 0);
    }

    $m->event->register('ocs_quick_view_header', 'catalog/view/common/header/before', 'extension/ocs_quick_view/events/beforeHeader', 9);
};
