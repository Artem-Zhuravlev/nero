<?php
declare(strict_types=1);

// Migration 0002: image sizes fixed by the design spec (SPEC-023 Data requirements, SPEC-006 REQ-THM-030).
// Only the roles the design defines are set; the other roles keep the values copied by 0001.
// Cached resized images regenerate on demand (image/cache). Rollback: re-set the values in the theme form in admin.

return function (\Ocs\Migrate\Context $m): void {
    $sizes = [
        'thumb' => [600, 600],
        'popup' => [1200, 1200],
        'product' => [400, 400],
        'additional' => [100, 100],
        'category' => [800, 800],
    ];
    foreach ($sizes as $role => [$w, $h]) {
        $m->setting->set('theme_ocs', "theme_ocs_image_{$role}_width", $w);
        $m->setting->set('theme_ocs', "theme_ocs_image_{$role}_height", $h);
    }
};
