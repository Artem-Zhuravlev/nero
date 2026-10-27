# PLAN — SPEC-076 Image Resize Guard

## Approach

Commit the already written OCMOD and migration 0010 as-is, and the 0008 loop fix. No new code; add spec, README row, rebuild archive.

## Affected files

- `custom/ocmod/ocs_image_resize.ocmod.xml`, `custom/db/migrations/0010_image_resize_ocmod.php`, `custom/db/migrations/0008_remove_cities.php`, `dist/ocs-theme-<VERSION>.ocmod.zip`, `specs/README.md`

## Risks

- Migration 0008 was already applied on existing databases; the edit only matters for fresh installs.
- The OCMOD and the 0010 migration have not been run against a real MySQL/MariaDB server in this repo.
