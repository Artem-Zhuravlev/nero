<?php
declare(strict_types=1);

// ocs_product_extras 004: admin reply to a review and admin-side save of pros / cons / reply (SPEC-116): column, admin save events, review form OCMOD refresh.
// Rollback: unregister the events; the column is kept (REQ-EXT-052).

return function (\Ocs\Migrate\Context $m): void {
    $m->addColumnIfMissing('ocs_review_extra', 'reply', 'TEXT NULL');

    $events = [
        'ocs_product_extras_review_admin_add' => ['admin/model/catalog/review/addReview/after', 'extension/ocs_product_extras/events/afterAddReview'],
        'ocs_product_extras_review_admin_edit' => ['admin/model/catalog/review/editReview/after', 'extension/ocs_product_extras/events/afterEditReview'],
    ];
    foreach ($events as $code => [$trigger, $action]) {
        $m->event->register($code, $trigger, $action, 5);
    }

    $m->ocmod->install('custom/ocmod/ocs_review_extra.ocmod.xml');
};
