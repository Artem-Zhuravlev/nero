<?php
declare(strict_types=1);

// ocs_back_in_stock 004: header view event hands the subscription form URL to out-of-stock cards (SPEC-077 REQ-OOS-001..003).
// Rollback: disable the event; out-of-stock cards then show no bell button.

return function (\Ocs\Migrate\Context $m): void {
    $m->event->register('ocs_back_in_stock_header_before', 'catalog/view/common/header/before', 'extension/ocs_back_in_stock/events/beforeHeader', 2);
};
