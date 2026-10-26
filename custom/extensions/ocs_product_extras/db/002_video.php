<?php
declare(strict_types=1);

// ocs_product_extras 002: YouTube video of a product (SPEC-048): table and the view event that flags product rows with a video.
// Rollback: unregister the event; the table is kept (REQ-EXT-052).

return function (\Ocs\Migrate\Context $m): void {
    $m->createTable('ocs_product_video', "CREATE TABLE IF NOT EXISTS {{PREFIX}}ocs_product_video (
        product_id INT NOT NULL,
        video_id CHAR(11) NOT NULL,
        PRIMARY KEY (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $m->event->register('ocs_product_extras_cards', 'catalog/view/*/before', 'extension/ocs_product_extras/events/markVideo', 60);
};
