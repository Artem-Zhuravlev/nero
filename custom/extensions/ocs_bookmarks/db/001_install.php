<?php
declare(strict_types=1);

// ocs_bookmarks 001: events that hydrate the session from the bookmark cookies and hand the card state to the theme (SPEC-044).
// Rollback: unregister the four events; wishlist and compare fall back to the core session behaviour.

return function (\Ocs\Migrate\Context $m): void {
    $m->event->register('ocs_bookmarks_hydrate_header', 'catalog/controller/common/header/before', 'extension/ocs_bookmarks/events/hydrate', 1);
    $m->event->register('ocs_bookmarks_hydrate_compare', 'catalog/controller/product/compare/before', 'extension/ocs_bookmarks/events/hydrate', 1);
    $m->event->register('ocs_bookmarks_hydrate_wishlist', 'catalog/controller/extension/ocs_bookmarks/wishlist/before', 'extension/ocs_bookmarks/events/hydrate', 1);
    $m->event->register('ocs_bookmarks_view', 'catalog/view/*/before', 'extension/ocs_bookmarks/events/beforeView', 50);
};
