<?php
declare(strict_types=1);

// ocs_topbar 001: register the module, grant admin permission, defaults (status 0, REQ-EXT-031), header view event (SPEC-082).
// Rollback: set module_ocs_topbar_status to 0; the event then returns immediately and the old ocs_shell announcement shows again.

return function (\Ocs\Migrate\Context $m): void {
    $m->extension->register('module', 'ocs_topbar');
    $m->permission->grant(1, 'extension/module/ocs_topbar');

    $defaults = [
        'module_ocs_topbar_status' => 0,
        'module_ocs_topbar_bg' => 'accent',
        'module_ocs_topbar_bg_custom' => '',
        'module_ocs_topbar_from' => '',
        'module_ocs_topbar_to' => '',
        'module_ocs_topbar_closable' => 1,
        'module_ocs_topbar_btn_style' => 'solid',
        'module_ocs_topbar_blank' => 0,
    ];
    foreach ($defaults as $key => $value) {
        if ($m->setting->get('module_ocs_topbar', $key) === null) {
            $m->setting->set('module_ocs_topbar', $key, $value);
        }
    }
    if ($m->setting->get('module_ocs_topbar', 'module_ocs_topbar_l') === null) {
        $m->setting->set('module_ocs_topbar', 'module_ocs_topbar_l', []);
    }

    $m->event->register('ocs_topbar_header_before', 'catalog/view/common/header/before', 'extension/ocs_topbar/events/beforeHeader', 1);
};
