<?php
declare(strict_types=1);

// ocs_ajax_cart 003: card data for the related products of the product page and the blog article (SPEC-099 REQ-PBU-002).
// Rollback: disable the two events; the related cards lose the extra keys but still render.

return function (\Ocs\Migrate\Context $m): void {
    foreach (['product/product' => 'product', 'blog/article' => 'article'] as $route => $key) {
        $m->event->register('ocs_ajax_cart_related_' . $key, 'catalog/view/' . $route . '/before', 'extension/ocs_ajax_cart/events/beforeModule', 3);
    }
};
