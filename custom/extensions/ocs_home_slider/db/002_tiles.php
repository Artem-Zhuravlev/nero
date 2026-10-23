<?php
declare(strict_types=1);

// ocs_home_slider 002: switch for the two promo tiles beside the slider, off by default (SPEC-038 REQ-LAY-006).
// Rollback: set module_ocs_home_slider_tiles to 0.

return function (\Ocs\Migrate\Context $m): void {
    if ($m->setting->get('module_ocs_home_slider', 'module_ocs_home_slider_tiles') === null) {
        $m->setting->set('module_ocs_home_slider', 'module_ocs_home_slider_tiles', 0);
    }
};
