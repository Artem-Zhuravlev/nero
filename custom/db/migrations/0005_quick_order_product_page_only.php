<?php
declare(strict_types=1);

// Migration 0005: the quick-order button is shown on the product page only (SPEC-044 REQ-QOR-040).
// Removes the listing events of SPEC-025. Rollback: re-register the four events (see git history of ocs_quick_order/db/001_install.php).

return function (\Ocs\Migrate\Context $m): void {
    foreach (['category', 'search', 'manufacturer', 'special'] as $page) {
        $m->event->unregisterByCode('ocs_quick_order_' . $page . '_before');
    }
};
