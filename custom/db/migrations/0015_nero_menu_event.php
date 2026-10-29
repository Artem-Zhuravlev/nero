<?php
declare(strict_types=1);

// Migration 0015: main admin menu entry "Nero settings" (SPEC-123 REQ-NST-005, 012).
// Rollback: unregister the event ocs_admin_nero_menu.

return function (\Ocs\Migrate\Context $m): void {
    $m->event->register('ocs_admin_nero_menu', 'admin/view/common/column_left/before', 'extension/ocs/events/beforeMenu', 1);
};
