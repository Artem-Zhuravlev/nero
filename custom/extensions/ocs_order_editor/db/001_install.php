<?php
declare(strict_types=1);

// ocs_order_editor 001: kill switch setting and the admin view event that swaps the order form template (SPEC-122).
// Rollback: set ocs_order_editor_status to 0 (the event returns at once and the stock core form is used) or unregister the event.

return function (\Ocs\Migrate\Context $m): void {
    if ($m->setting->get('ocs_order_editor', 'ocs_order_editor_status') === null) {
        $m->setting->set('ocs_order_editor', 'ocs_order_editor_status', 1);
    }

    $m->event->register('ocs_order_editor_form', 'admin/view/sale/order_form/before', 'extension/ocs_order_editor/events/beforeOrderForm', 5);
};
