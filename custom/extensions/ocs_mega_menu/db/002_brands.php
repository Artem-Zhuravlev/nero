<?php
declare(strict_types=1);

// ocs_mega_menu 002: brand group in the fly-out (SPEC-027 REQ-MNU-002). Off by default; safe to re-run.

return function (\Ocs\Migrate\Context $m): void {
    foreach (['module_ocs_mega_menu_brands' => 0, 'module_ocs_mega_menu_brands_limit' => 8] as $key => $value) {
        if ($m->setting->get('module_ocs_mega_menu', $key) === null) {
            $m->setting->set('module_ocs_mega_menu', $key, $value);
        }
    }
};
