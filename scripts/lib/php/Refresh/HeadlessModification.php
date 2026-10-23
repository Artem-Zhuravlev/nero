<?php
declare(strict_types=1);

// Loaded lazily by Ocs\Refresh\Refresher AFTER the admin constants and core system/startup.php exist.
// Reuses core's own OCMOD algorithm (admin/controller/marketplace/modification.php, refresh()) so patched output is
// identical to the admin "Refresh" button (SPEC-005 REQ-DB-040). Core files are only read.

require_once DIR_APPLICATION . 'controller/marketplace/modification.php';

/**
 * Same behaviour as the admin controller, minus the web-only parts: no permission check and no list rendering.
 */
class OcsHeadlessModification extends ControllerMarketplaceModification
{
    protected function validate()
    {
        return true;
    }

    protected function getList()
    {
    }
}
