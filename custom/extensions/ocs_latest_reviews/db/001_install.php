<?php
declare(strict_types=1);

// ocs_latest_reviews 001: register the module and its permission; instances live in the core module table (SPEC-081 REQ-LRV-001).
// Rollback: delete the instances in Extensions > Modules.

return function (\Ocs\Migrate\Context $m): void {
    $m->extension->register('module', 'ocs_latest_reviews');
    $m->permission->grant(1, 'extension/module/ocs_latest_reviews');
};
