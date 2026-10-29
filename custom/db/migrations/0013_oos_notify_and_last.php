<?php
declare(strict_types=1);

// Migration 0013: out-of-stock bell and out-of-stock-last ordering (SPEC-120 REQ-OOS2-003, 004).
// Enables the back-in-stock module (it was installed with status 0, which hid the bell) and installs the listing ordering OCMOD.
// Idempotent. Rollback: set module_ocs_back_in_stock_status to 0; remove the modification 'ocs_listing_stock_last' and refresh modifications.

return function (\Ocs\Migrate\Context $m): void {
    $m->setting->set('module_ocs_back_in_stock', 'module_ocs_back_in_stock_status', 1);
    $m->ocmod->install('custom/ocmod/ocs_listing_stock_last.ocmod.xml');
};
