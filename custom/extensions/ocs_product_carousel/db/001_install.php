<?php
declare(strict_types=1);

// ocs_product_carousel 001: register the module and its permission; instances live in the core module table (SPEC-033 REQ-HOM-020).
// Rollback: delete the instances in Extensions > Modules.

return function (\Ocs\Migrate\Context $m): void {
    $m->extension->register('module', 'ocs_product_carousel');
    $m->permission->grant(1, 'extension/module/ocs_product_carousel');
};
