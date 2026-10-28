<?php
declare(strict_types=1);

// Migration 0012: instalments and trade-in are removed (SPEC-087 REQ-RCI-002, 003). Unregisters the modules, events, permissions
// and settings, drops the trade-in lead table. Idempotent; leads are not recoverable (restore the SPEC-036 commit to bring the feature back).

return function (\Ocs\Migrate\Context $m): void {
    foreach (['ocs_credit_product', 'ocs_tradein_product', 'ocs_tradein_category'] as $code) {
        $m->event->unregisterByCode($code);
    }
    foreach (['ocs_credit', 'ocs_tradein'] as $ext) {
        $m->extension->unregister('module', $ext);
        $m->permission->revoke(1, 'extension/module/' . $ext);
    }
    foreach (['status', 'plans', 'l'] as $key) {
        $m->setting->delete('module_ocs_credit', 'module_ocs_credit_' . $key);
    }
    foreach (['status', 'categories', 'email', 'l'] as $key) {
        $m->setting->delete('module_ocs_tradein', 'module_ocs_tradein_' . $key);
    }

    $m->sql('DROP TABLE IF EXISTS {{PREFIX}}ocs_tradein_lead');
};
