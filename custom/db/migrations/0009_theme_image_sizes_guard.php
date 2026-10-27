<?php
declare(strict_types=1);

// Migration 0009: the live site fataled with "Image::resize() must be of the type int, null given" on category pages:
// theme_ocs_image_*_width/height were missing or empty, core passes them straight to the resizer.
// Backfills every missing/empty theme_ocs image size and registers a guard event on catalog/model/tool/image/resize
// (extension/ocs/events/beforeImageResize) that replaces a null/zero size by a default.
// Idempotent. Rollback: unregister the event (the size settings can stay).

return function (\Ocs\Migrate\Context $m): void {
    $defaults = [
        'thumb' => [228, 228], 'popup' => [500, 500], 'category' => [80, 80], 'manufacturer' => [80, 80],
        'product' => [228, 228], 'additional' => [74, 74], 'related' => [80, 80], 'compare' => [90, 90],
        'wishlist' => [47, 47], 'cart' => [47, 47], 'location' => [268, 50],
    ];
    foreach ($defaults as $role => [$w, $h]) {
        foreach (['width' => $w, 'height' => $h] as $dim => $fallback) {
            $key = "theme_ocs_image_{$role}_{$dim}";
            $current = $m->setting->get('theme_ocs', $key);
            if ($current === null || $current === '' || (int)$current <= 0) {
                $m->setting->set('theme_ocs', $key, $fallback);
            }
        }
    }
    foreach (['product_limit' => 15, 'product_description_length' => 100] as $field => $fallback) {
        $current = $m->setting->get('theme_ocs', "theme_ocs_{$field}");
        if ($current === null || $current === '' || (int)$current <= 0) {
            $m->setting->set('theme_ocs', "theme_ocs_{$field}", $fallback);
        }
    }

    $m->event->register('ocs_image_resize_guard', 'catalog/model/tool/image/resize/before', 'extension/ocs/events/beforeImageResize', 0);
};
