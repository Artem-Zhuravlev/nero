<?php
declare(strict_types=1);

// ocs_listing 001: listing enhancements of the ocs theme (SPEC-034): OCMOD for the popularity sort key, view events.
// Unlike REQ-EXT-031 the switch defaults to 1: this is part of the theme's listing templates (owner spec SPEC-034).
// Rollback: set library_ocs_listing_status to 0 - the events return immediately and templates fall back to core markup.
// An OCMOD refresh (scripts/refresh.php) is required after this migration (REQ-OCM-026).

return function (\Ocs\Migrate\Context $m): void {
    if ($m->setting->get('library_ocs_listing', 'library_ocs_listing_status') === null) {
        $m->setting->set('library_ocs_listing', 'library_ocs_listing_status', 1);
    }

    $m->ocmod->install('custom/ocmod/ocs_listing_sort.ocmod.xml');

    $m->event->register('ocs_listing_category_before', 'catalog/view/product/category/before', 'extension/ocs_listing/events/beforeCategory', 1);
    $m->event->register('ocs_listing_search_before', 'catalog/view/product/search/before', 'extension/ocs_listing/events/beforeSearch', 1);
    $m->event->register('ocs_listing_manufacturer_before', 'catalog/view/product/manufacturer_info/before', 'extension/ocs_listing/events/beforeManufacturer', 1);
    $m->event->register('ocs_listing_special_before', 'catalog/view/product/special/before', 'extension/ocs_listing/events/beforeSpecial', 1);
};
