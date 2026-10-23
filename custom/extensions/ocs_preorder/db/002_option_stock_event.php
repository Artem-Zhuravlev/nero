<?php
declare(strict_types=1);

// ocs_preorder 002: option-level stock rule, the shared handler keeps out-of-stock option values selectable (SPEC-037 REQ-OPT-001).
// Rollback: disable the event; option values that are out of stock are hidden again by the core.

return function (\Ocs\Migrate\Context $m): void {
    $m->event->register('ocs_preorder_options', 'catalog/view/product/product/before', 'extension/ocs_request/events/beforeProduct', 0);
};
