# TASKS — SPEC-001 Project Architecture

| Task | Description | Fulfills | Check | Status |
| ---- | ----------- | -------- | ----- | ------ |
| TASK-ARCH-001 | Move vanilla dist to `core/`, generate `core/.manifest.sha256` | REQ-ARCH-001, 010 | TEST-ARCH-001 manifest verify | pending |
| TASK-ARCH-002 | Repo skeleton: `custom/*`, `scripts/`, `docs/`, `.gitignore`, `.env.example`, `package.json` | REQ-ARCH-011, 013 | TEST-ARCH-002 layout check | pending |
| TASK-ARCH-003 | `scripts/lib/env.mjs` (parse/validate `.env`) | REQ-ARCH-015, 043, 044 | TEST-ARCH-003 missing key fails | pending |
| TASK-ARCH-004 | `scripts/build.mjs` overlay + collision check + incremental copy + preserve `image/catalog`,`image/cache` | REQ-ARCH-012, 014, 015, 016 | TEST-ARCH-004 collision negative test | pending |
| TASK-ARCH-005 | Config templates + renderer, admin dir rename, storage skeleton | REQ-ARCH-033, 040, 041, 042 | TEST-ARCH-005 rendered config has no `{{` | pending |
| TASK-ARCH-006 | `.htaccess` generation + deny-list | REQ-ARCH-061 | TEST-ARCH-006 smoke denied paths | pending |
| TASK-ARCH-007 | `scripts/migrate.php` + `oc_ocs_migration` + checksums + `0001_baseline.sql` | REQ-ARCH-051, 053, 055 | TEST-ARCH-007 edited-migration negative test | pending |
| TASK-ARCH-008 | `scripts/seed.php` (idempotent) | REQ-ARCH-052 | TEST-ARCH-008 double-run no diff | pending |
| TASK-ARCH-009 | `scripts/refresh.php` (OCMOD refresh + cache clear) | REQ-ARCH-054, 021 | TEST-ARCH-009 modification dir populated | pending |
| TASK-ARCH-010 | Extension scaffolder `scripts/new-extension.mjs` + sample `ocs_hello` module | REQ-ARCH-024..027, 030, 031, 034 | TEST-ARCH-010 builds & renders in storefront | pending |
| TASK-ARCH-011 | Theme skeleton `custom/theme/ocs` + `extension/theme/ocs` admin entry, selected by migration | REQ-ARCH-020 | TEST-ARCH-011 home renders with theme `ocs` | pending |
| TASK-ARCH-012 | `lint-layers.mjs` (no SQL in controllers/views, no `@`, no `eval`, namespace in libs) | REQ-ARCH-031, 034, 035, 063 | TEST-ARCH-012 fixtures | pending |
| TASK-ARCH-013 | i18n parity check | REQ-ARCH-025, 092 | TEST-ARCH-013 | pending |
| TASK-ARCH-014 | `scripts/smoke.mjs` | REQ-ARCH-081, 060 | TEST-ARCH-014 | pending |
| TASK-ARCH-015 | `scripts/verify.mjs` orchestration + traceability check | REQ-ARCH-080 | TEST-ARCH-015 | pending |
| TASK-ARCH-016 | `scripts/backup.mjs` | REQ-ARCH-056 | TEST-ARCH-016 | pending |
| TASK-ARCH-017 | Production guard `verify --env production` | REQ-ARCH-062 | TEST-ARCH-017 | pending |
| TASK-ARCH-018 | `docs/coding-rules.md`, `docs/architecture-overview.md` | REQ-ARCH-083 | review | pending |
| TASK-ARCH-019 | Core-dependency header scanner (`@core-file`) and upgrade report | REQ-ARCH-100, 101, 102 | TEST-ARCH-019 | pending |
| TASK-ARCH-020 | Cache-key and N+1 review checklist in coding rules | REQ-ARCH-110, 111, 113 | review | pending |
| TASK-ARCH-021 | Asset versioning from BUILD_INFO | REQ-ARCH-114 | TEST-ARCH-021 | pending |
| TASK-ARCH-022 | Logging/error rules lint (no swallow, no secrets in log calls) | REQ-ARCH-120, 121, 122 | TEST-ARCH-022 | pending |
| TASK-ARCH-023 | SEO/route namespace lint (`extension/ocs_*` only, `url->link` only) | REQ-ARCH-130..133 | TEST-ARCH-023 | pending |
| TASK-ARCH-024 | i18n/currency/store rules lint | REQ-ARCH-140, 141, 142 | TEST-ARCH-024 | pending |
| TASK-ARCH-025 | `scripts/run-php-tests.php` minimal runner + stub registry | REQ-ARCH-150, 151, 152 | TEST-ARCH-025 | pending |
| TASK-ARCH-026 | Docs upkeep gate (spec touching platform must touch docs) | REQ-ARCH-160, 161, 162 | review | pending |
| TASK-ARCH-027 | Platform prerequisites and PHP policy documented/checked (extensions list, version, error levels) | REQ-ARCH-002, 003, 004 | TEST-ARCH-027 | pending |
| TASK-ARCH-028 | Forbid vQmod and vendored-extension rules (VENDOR.md) in verify | REQ-ARCH-022, 023 | TEST-ARCH-028 | pending |
| TASK-ARCH-029 | Installer-in-place note: no `install/` in docroot, installer wrapper contract | REQ-ARCH-032 | TEST-ARCH-029 | pending |
| TASK-ARCH-030 | Settings-as-code convention check (settings only via migrations in non-local) | REQ-ARCH-050 | TEST-ARCH-030 | pending |
| TASK-ARCH-031 | Language set declaration + overrides convention | REQ-ARCH-090, 091 | TEST-ARCH-031 | pending |
| TASK-ARCH-032 | Admin permission and upload rules lint | REQ-ARCH-064, 065 | TEST-ARCH-032 | pending |
| TASK-ARCH-033 | Commit-message and branch policy hook/check | REQ-ARCH-082 | TEST-ARCH-033 | pending |
