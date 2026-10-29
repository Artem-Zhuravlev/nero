<?php
declare(strict_types=1);

// Migration 0014: font choice of the theme (SPEC-121 REQ-FCH-001). Default keeps the current look (Montserrat).
// Rollback: delete the setting theme_ocs_font (the theme falls back to Montserrat).

return function (\Ocs\Migrate\Context $m): void {
    if ($m->setting->get('theme_ocs', 'theme_ocs_font') === null) {
        $m->setting->set('theme_ocs', 'theme_ocs_font', 'montserrat');
    }
};
