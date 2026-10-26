<?php
declare(strict_types=1);

// ocs_product_extras 003: pros and cons of reviews (SPEC-049): table, storefront save / display events, admin display and cleanup, admin form OCMOD.
// Rollback: unregister the events; the table is kept (REQ-EXT-052).

return function (\Ocs\Migrate\Context $m): void {
    $m->createTable('ocs_review_extra', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_review_extra (
        review_id INT NOT NULL,
        pros VARCHAR(255) NOT NULL DEFAULT '',
        cons VARCHAR(255) NOT NULL DEFAULT '',
        PRIMARY KEY (review_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $events = [
        'ocs_product_extras_review_add' => ['catalog/model/catalog/review/addReview/after', 'extension/ocs_product_extras/events/afterAddReview'],
        'ocs_product_extras_review_list' => ['catalog/view/product/review/before', 'extension/ocs_product_extras/events/beforeReviewList'],
        'ocs_product_extras_review_form' => ['admin/view/catalog/review_form/before', 'extension/ocs_product_extras/events/beforeReviewForm'],
        'ocs_product_extras_review_delete' => ['admin/model/catalog/review/deleteReview/before', 'extension/ocs_product_extras/events/beforeDeleteReview'],
    ];
    foreach ($events as $code => [$trigger, $action]) {
        $m->event->register($code, $trigger, $action, 5);
    }

    $m->ocmod->install('custom/ocmod/ocs_review_extra.ocmod.xml');
};
