<?php
declare(strict_types=1);

// ocs_shell 001: register the module, grant admin permission, defaults (status 0, REQ-EXT-031), view events (SPEC-031).
// Rollback: set module_ocs_shell_status to 0; the events then return immediately and templates fall back to core markup.

return function (\Ocs\Migrate\Context $m): void {
    $m->extension->register('module', 'ocs_shell');
    $m->permission->grant(1, 'extension/module/ocs_shell');

    $defaults = [
        'module_ocs_shell_status' => 0,
        'module_ocs_shell_mobile_bar' => 1,
        'module_ocs_shell_announcement_from' => '',
        'module_ocs_shell_announcement_to' => '',
        'module_ocs_shell_phones' => '',
        'module_ocs_shell_telegram' => '',
        'module_ocs_shell_viber' => '',
        'module_ocs_shell_messenger' => '',
        'module_ocs_shell_social' => '',
        'module_ocs_shell_apps' => '',
    ];
    foreach ($defaults as $key => $value) {
        if ($m->setting->get('module_ocs_shell', $key) === null) {
            $m->setting->set('module_ocs_shell', $key, $value);
        }
    }
    if ($m->setting->get('module_ocs_shell', 'module_ocs_shell_l') === null) {
        $m->setting->set('module_ocs_shell', 'module_ocs_shell_l', []);
    }

    $m->event->register('ocs_shell_header_before', 'catalog/view/common/header/before', 'extension/ocs_shell/events/beforeHeader', 1);
    $m->event->register('ocs_shell_footer_before', 'catalog/view/common/footer/before', 'extension/ocs_shell/events/beforeFooter', 1);
};
