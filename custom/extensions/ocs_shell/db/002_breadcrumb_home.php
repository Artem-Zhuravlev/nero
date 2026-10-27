<?php
declare(strict_types=1);

// ocs_shell 002: view event that replaces the icon "home" breadcrumb with a word (SPEC-054).
// Rollback: remove the event; breadcrumbs then show the core home text.

return function (\Ocs\Migrate\Context $m): void {
    $m->event->register('ocs_shell_breadcrumb_home', 'catalog/view/*/before', 'extension/ocs_shell/events/beforeView', 2);
};
