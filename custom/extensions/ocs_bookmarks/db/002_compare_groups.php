<?php
declare(strict_types=1);

// ocs_bookmarks 002: compare page by category - narrow the compared products to the active group, build tabs and table rows (SPEC-057).
// Rollback: unregister the two events; the compare page falls back to the flat table of all compared products.

return function (\Ocs\Migrate\Context $m): void {
    $m->event->register('ocs_bookmarks_compare_filter', 'catalog/controller/product/compare/before', 'extension/ocs_bookmarks/events/compareFilter', 2);
    $m->event->register('ocs_bookmarks_compare_view', 'catalog/view/product/compare/before', 'extension/ocs_bookmarks/events/compareView', 20);
};
