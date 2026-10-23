<?php
declare(strict_types=1);

// Migration 0001: register and activate the storefront theme `ocs` (SPEC-006 REQ-THM-002, REQ-THM-003).
//
// Order matters: core throws "A theme has not been assigned" when theme_<name>_status is falsy, so the
// status is written BEFORE config_theme.
// Rollback: set `config_theme` to `default` for store 0 (nothing else needs reverting, REQ-THM-061).

return function (\Ocs\Migrate\Context $m): void {
    $roles = ['thumb', 'popup', 'category', 'manufacturer', 'product', 'additional', 'related', 'compare', 'wishlist', 'cart', 'location'];
    $fallback = [
        'product_limit' => 15, 'product_description_length' => 100,
        'image_thumb_width' => 228, 'image_thumb_height' => 228,
        'image_popup_width' => 500, 'image_popup_height' => 500,
        'image_category_width' => 80, 'image_category_height' => 80,
        'image_manufacturer_width' => 80, 'image_manufacturer_height' => 80,
        'image_product_width' => 228, 'image_product_height' => 228,
        'image_additional_width' => 74, 'image_additional_height' => 74,
        'image_related_width' => 80, 'image_related_height' => 80,
        'image_compare_width' => 90, 'image_compare_height' => 90,
        'image_wishlist_width' => 47, 'image_wishlist_height' => 47,
        'image_cart_width' => 47, 'image_cart_height' => 47,
        'image_location_width' => 268, 'image_location_height' => 50,
    ];

    $m->extension->register('theme', 'ocs');
    $m->permission->grant(1, 'extension/theme/ocs');

    // Values present in theme_default_* at migration time win, existing theme_ocs_* values are never overwritten.
    $fields = ['product_limit', 'product_description_length'];
    foreach ($roles as $role) {
        $fields[] = "image_{$role}_width";
        $fields[] = "image_{$role}_height";
    }
    foreach ($fields as $field) {
        if ($m->setting->get('theme_ocs', "theme_ocs_{$field}") !== null) {
            continue;
        }
        $value = $m->setting->get('theme_default', "theme_default_{$field}");
        $m->setting->set('theme_ocs', "theme_ocs_{$field}", $value !== null ? $value : $fallback[$field]);
    }

    $m->setting->set('theme_ocs', 'theme_ocs_directory', 'ocs');
    $m->setting->set('theme_ocs', 'theme_ocs_status', 1);

    $m->event->register('ocs_theme_header_before', 'catalog/view/common/header/before', 'extension/ocs/events/beforeHeader', 0);

    $m->setting->set('config', 'config_theme', 'ocs');
};
