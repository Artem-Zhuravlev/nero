<?php
declare(strict_types=1);

// ocs_home_slider 003: category menu beside the banners and the multi-banner look, both off by default (SPEC-039 REQ-VIS-003).
// Rollback: set both settings to 0.

return function (\Ocs\Migrate\Context $m): void {
    foreach (['module_ocs_home_slider_menu', 'module_ocs_home_slider_multi'] as $key) {
        if ($m->setting->get('module_ocs_home_slider', $key) === null) {
            $m->setting->set('module_ocs_home_slider', $key, 0);
        }
    }
};
