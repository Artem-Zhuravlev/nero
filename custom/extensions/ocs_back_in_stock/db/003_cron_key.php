<?php
declare(strict_types=1);

// ocs_back_in_stock 003: secret key of the cron endpoint, generated once (SPEC-037 REQ-OPT-002).
// Rollback: delete the setting; the endpoint then answers every call with an empty 404.

return function (\Ocs\Migrate\Context $m): void {
    $key = $m->setting->get('module_ocs_back_in_stock', 'module_ocs_back_in_stock_cron_key');

    if ($key === null || strlen((string) $key) < 16) {
        $m->setting->set('module_ocs_back_in_stock', 'module_ocs_back_in_stock_cron_key', bin2hex(random_bytes(16)));
    }
};
