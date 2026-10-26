<?php
declare(strict_types=1);

// Migration 0006: stock label / article number switches of the theme (SPEC-044 REQ-VIS-050, 051).
// Defaults on (current behaviour); the event runs last (sort 99) over every storefront view.
// Rollback: unregister the event; delete the two settings.

return function (\Ocs\Migrate\Context $m): void {
    foreach (['theme_ocs_show_stock', 'theme_ocs_show_sku'] as $key) {
        if ($m->setting->get('theme_ocs', $key) === null) {
            $m->setting->set('theme_ocs', $key, 1);
        }
    }

    $m->event->register('ocs_theme_display', 'catalog/view/*/before', 'extension/ocs/events/applyDisplay', 99);
};
