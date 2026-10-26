<?php
declare(strict_types=1);

// Migration 0004: the quick view is removed (SPEC-044 REQ-QVR-001). Uninstalls the module, its event, permission and setting.
// Rollback: none needed; the code is gone (restore the SPEC-037 commit to bring it back).

return function (\Ocs\Migrate\Context $m): void {
    $m->event->unregisterByCode('ocs_quick_view_header');
    $m->extension->unregister('module', 'ocs_quick_view');
    $m->permission->revoke(1, 'extension/module/ocs_quick_view');
    $m->setting->delete('module_ocs_quick_view', 'module_ocs_quick_view_status');
};
