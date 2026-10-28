<?php
declare(strict_types=1);

// ocs_listing 003: page number in title and meta description of category and manufacturer pages after the first (SPEC-094 REQ-PGSEO-002).
// Rollback: disable the event; titles lose the suffix.

return function (\Ocs\Migrate\Context $m): void {
    $m->event->register('ocs_listing_header_page', 'catalog/view/common/header/before', 'extension/ocs_listing/events/beforeHeader', 1);
};
