# SPEC-040 — Installable Theme Package (ocs-theme.ocmod.zip)

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-26
**Refines:** SPEC-001, SPEC-004, SPEC-005, SPEC-006, SPEC-007 · **Supersedes:** the first draft of SPEC-040 (full demo stand with bundled PHP/MariaDB), rejected on review

## Problem

The theme `ocs` and its 25 `ocs_*` modules live in `custom/` and reach a store only through this repo's build overlay and migrations.
A colleague who already has an ocStore/OpenCart 3 cannot add the theme without deploying the whole project. OpenCart's own
mechanism for this is an extension package installed in the admin (Extensions -> Installer), which the project does not produce.

## Purpose

`npm run pack:theme` writes one file, `dist/ocs-theme-<version>.ocmod.zip`. A colleague uploads it in the admin of their existing
ocStore 3.0.3.x, clicks one "Install" button on the theme page, selects the theme in settings and sees the storefront.
Nothing else of the project (core, build, scripts, `.env`) is required on their side.

## Scope

- Packer `scripts/pack-theme.mjs` + `npm run pack:theme`: merges `custom/theme/ocs` and every `custom/extensions/*/upload` into one
  `upload/` tree, merges `custom/ocmod/*.ocmod.xml` into one `install.xml`, adds an in-package installer, zips.
- In-package installer: admin route `extension/ocs/setup` (opened from the theme's settings page) that runs the package's
  migrations (tables, settings, events, permissions, theme registration, layouts, modules) idempotently through the store's own
  DB connection, then refreshes modifications. Reports created/updated/unchanged. Has an "uninstall" counterpart that is
  non-destructive by default (switches the theme back to `default`, deregisters events/modules, keeps data tables).
- Optional demo content: a separate button "Fill demo data" (the `seed-demo` equivalent) that is OFF by default and never runs
  implicitly.
- Compatibility check at install: ocStore/OpenCart >= 3.0.3.x, PHP >= 7.1, MySQL 5.7+/8 or MariaDB 10.x, required tables present; clear error otherwise.
- `README-INSTALL.md` (Russian) inside the zip and in `docs/`.

## Out of scope

Editing `core/`; supporting OpenCart 2/4; the full-stand zip with bundled PHP/MariaDB (dropped); Marketplace publishing; multi-store
specifics beyond store 0; shipping product/catalogue data of the author's shop.

## Functional requirements

- **REQ-PKG-001** — `npm run pack:theme` produces `dist/ocs-theme-<version>.ocmod.zip` (version from a `VERSION` in `custom/`),
  deterministic for the same inputs, and prints file count and size.
- **REQ-PKG-002** — The zip contains only paths the core installer accepts (`admin/controller/extension/`, `admin/language/`,
  `admin/model/extension/`, `admin/view/...`, `catalog/controller|language|model/extension/`, `catalog/view/theme/`,
  `catalog/view/javascript/`, `system/library/`, `system/config/`, `image/catalog/`); the packer fails on any other path.
  The packer never touches `core/`.
- **REQ-PKG-003** — The theme (templates, CSS, JS, fonts) is installed under `catalog/view/theme/ocs/`; all modules and their
  language files, events and libraries are installed under their usual `extension/...` and `system/library/ocs/` paths.
- **REQ-PKG-004** — The project OCMODs travel inside the package payload and are installed into `oc_modification` by the same migrations that install them in the dev flow (one code path; no `install.xml`, so the standard installer never owns or removes them); Setup refreshes modifications afterwards.
- **REQ-PKG-005** — The package's `setup` route runs migrations in numeric order, records what ran in the store's own DB
  (reusing `ocs_migration`), and is safe to re-run (idempotent, REQ-DB-*); a re-run after upgrade applies only new ones.
- **REQ-PKG-006** — After Setup the theme `ocs` is registered and selectable under System -> Settings -> Option -> Theme; Setup never switches the active theme (the dev-stand migration's `config_theme` write is reverted). A separate "Activate" action selects the theme and switches all `module_ocs_*` on.
- **REQ-PKG-007** — Setup creates a DB backup hint (list of tables/settings it will touch) before running and aborts without
  partial changes when the pre-check fails.
- **REQ-PKG-008** — Modules are installed but disabled until "Activate" (no change to the existing storefront before that).
- **REQ-PKG-009** — "Demo content" (shared `Ocs\Setup\DemoSeed`, also used by `scripts/seed-demo.php`) is idempotent, opt-in, touches only `ocs_*` tables/settings/modules and layout positions (home layout), and never creates or deletes catalogue data; categories/images of the stock demo catalogue are used when present and skipped otherwise.
- **REQ-PKG-010** — Uninstall leaves the shop working: theme -> `default`, `module_ocs_*` off, OCS events and modifications removed, migration bookkeeping cleared so a later Setup re-applies; data tables and layout bindings are kept (inert while modules are off). Core's own theme "uninstall" button also resets `config_theme`.
- **REQ-PKG-011** — `README-INSTALL.md` covers: requirements, upload steps with screenshots-free numbered steps, activation,
  demo data, update, uninstall, troubleshooting (OCMOD refresh, permissions, cache), what changes in the DB.

## Non-functional requirements

- **REQ-PKG-020** — Package contains no secrets, `.env`, absolute dev paths or personal data (scan reuses SPEC-009 patterns).
- **REQ-PKG-021** — Setup code lives in `system/library/ocs/setup/` + one admin controller (thin); it reuses the existing
  migration classes (same code path as `npm run db:migrate`, no second implementation).
- **REQ-PKG-022** — Works with the stock `default` theme untouched; no files outside the allow-list are written.
- **REQ-PKG-023** — Size of the zip reported; target under 5 MB.

## Data requirements

No new tables: reuses migration tracking of SPEC-005. Package version stored in setting `ocs_package_version`.

## Interfaces

`npm run pack:theme [-- --out <dir>]`; admin routes `extension/ocs/setup` (+ actions `run`, `demo`, `uninstall`);
files in zip: `install.xml`, `upload/**`, `README-INSTALL.md`.

## Edge cases

- Installer re-uploaded over an old version -> files overwritten, `setup` shows pending migrations.
- Shop already uses another theme or SEO module -> theme only registered; nothing activated without a tick.
- OCMOD cache not refreshed -> setup triggers refresh and tells how to do it manually.
- `modification`/`storage` not writable -> pre-check error with the exact path.
- ocStore build without a table used by a module (e.g. blog) -> pre-check lists the missing table.
- Upload limit / FTP-only hosting -> README documents unzip-to-docroot as an alternative (same `upload/` tree).

## Acceptance criteria

- [x] `npm run pack:theme` creates the zip; its path list passes the allow-list check.
- [x] On a fresh stock ocStore 3.0.3.7 (this repo's core installed without custom code), uploading the zip via the admin installer
      and pressing Setup registers the theme and modules; selecting the theme renders home, category, product pages with no PHP errors.
- [x] Re-running Setup changes nothing; Uninstall returns the shop to `default`.
- [x] Scan green, `npm run verify` green.
