<?php
declare(strict_types=1);

// Migration 0011: schema.org JSON-LD for every storefront page (SPEC-080 REQ-SD-001, REQ-SD-009).
// Settings: status on, organization name / logo empty (= store name / logo). Existing values are kept.
// The event runs after every storefront view and puts one JSON-LD script before </head> of full pages.
// Rollback: unregister the event ocs_structured_data (or set theme_ocs_schema_status to 0); delete the three settings.

return function (\Ocs\Migrate\Context $m): void {
    foreach (['theme_ocs_schema_status' => 1, 'theme_ocs_schema_org_name' => '', 'theme_ocs_schema_org_logo' => ''] as $key => $value) {
        if ($m->setting->get('theme_ocs', $key) === null) {
            $m->setting->set('theme_ocs', $key, $value);
        }
    }

    $m->event->register('ocs_structured_data', 'catalog/view/*/after', 'extension/ocs/events/structuredData', 0);
};
