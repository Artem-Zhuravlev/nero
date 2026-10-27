# SPEC-053 — OCMOD as Files in system/

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-007 (OCMOD/events), SPEC-040 (theme package)

## Problem

The `ocs_*` OCMOD XML files are registered in `oc_modification` only by migrations. After a manual deletion of the
modifications, "Install / update" does not recreate them (migrations are already logged as applied), so the product form
tabs (Stickers, Accessories + YouTube video) vanish and cannot be restored without Uninstall.

## Purpose

Deliver the `ocs_*` OCMOD as `system/<code>.ocmod.xml` files, which core's modification refresh applies from disk
(`admin/controller/marketplace/modification.php`, `glob(DIR_SYSTEM . '*.ocmod.xml')`), independent of the database.

## Scope

- The Setup page ("Install / update" and "Activate") copies every packaged `ocs_*.ocmod.xml` to `system/` and then refreshes modifications.
- Migrations write `ocs_*` OCMOD as files (the helper); Setup drops leftover `ocs_*` rows on each install.
- Build overlay (`scripts/build.mjs`) places the same files in `build/www/system/` for the dev stand.

## Out of scope

- Changing the XML content; third-party OCMOD; the admin Modifications UI (core).

## Functional requirements

- **REQ-OCM-001** — Setup install copies `custom/ocmod/*.ocmod.xml` payload files to `DIR_SYSTEM`, overwriting identical-code files, and reports each in the log.
- **REQ-OCM-002** — `Ocmod::install()` writes the file when the context has an OCMOD dir (and deletes the same-code `oc_modification` row); Setup install also drops every leftover `ocs_*` row. No separate migration is needed.
- **REQ-OCM-003** — Setup uninstall deletes the `system/ocs_*.ocmod.xml` files.
- **REQ-OCM-004** — If `system/` is not writable, Setup reports it as a precheck problem naming the path.
- **REQ-OCM-005** — `npm run build` places the files into `build/www/system/`.
- **REQ-OCM-006** — The theme archive root contains `ocmod-xml/ocs_*.ocmod.xml` (copies for a manual copy into `system/`; the core installer ignores everything outside `upload/`).

## Non-functional requirements

- **REQ-OCM-010** — Running Setup twice leaves exactly one copy of each OCMOD (idempotent); no `ocs_*` row and file coexist.

## Data requirements

Files `system/ocs_*.ocmod.xml` (4 now); `oc_modification` loses `ocs_*` rows.

## Interfaces

Setup page (`extension/ocs/setup`), `scripts/lib/php/Migrate/Helper/Ocmod.php`, `scripts/lib/php/Setup/Service.php`, `scripts/build.mjs`.

## Edge cases

- Read-only `system/` -> precheck fails with the path (REQ-OCM-004).
- Old `ocs_*` DB rows left by earlier installs -> removed by Setup install (REQ-OCM-002).

## Acceptance criteria

- [ ] After deleting all modifications in admin and pressing Setup install, the Stickers and Accessories tabs are back after "Refresh".
- [ ] `oc_modification` has no `ocs_*` rows; `system/` holds the 4 files.
- [ ] `npm run verify` is green.
