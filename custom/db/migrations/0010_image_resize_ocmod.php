<?php
declare(strict_types=1);

// Migration 0010: installs the OCMOD that makes ModelToolImage::resize() fall back to a default size when the
// size is null/0 (live fatal "Image::resize() must be of the type int, null given" survived 0009's event guard).
// Idempotent. Rollback: remove the modification 'ocs_image_resize' in admin and refresh modifications.

return function (\Ocs\Migrate\Context $m): void {
    $m->ocmod->install('custom/ocmod/ocs_image_resize.ocmod.xml');
};
