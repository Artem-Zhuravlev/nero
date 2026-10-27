<?php
declare(strict_types=1);

// ocs_stickers 002: card data for the product modules featured, special, latest, bestseller, featured_product (SPEC-056 REQ-MPC-002).
// Rollback: disable the five events; module cards lose the extra keys but still render.

return function (\Ocs\Migrate\Context $m): void {
    foreach (['featured', 'special', 'latest', 'bestseller', 'featured_product'] as $module) {
        $m->event->register('ocs_stickers_module_' . $module, 'catalog/view/extension/module/' . $module . '/before', 'extension/ocs_stickers/events/beforeModule', 4);
    }
};
