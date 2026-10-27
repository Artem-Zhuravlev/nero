# PLAN — SPEC-053 OCMOD as Files in system/

## Approach
Change `Ocmod::install()` to write `DIR_SYSTEM/<code>.ocmod.xml` (Setup passes the target dir; CLI/build use `build/www/system`),
add migration 0009 deleting `ocs_*` DB rows, make Setup install re-sync the files on every run (not only inside migrations), and delete them on uninstall.

## Architecture decisions
| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Source of truth | files in `system/` | Survives deletion in admin; core supports it. Alternative: re-register rows on each Setup run — still deletable from the admin list. |
| Core files | untouched | `*.ocmod.xml` in system/ is a core-supported hook. |

## Affected files
`scripts/lib/php/Migrate/Helper/Ocmod.php`, `scripts/lib/php/Setup/Service.php`, `custom/extensions/ocs/upload/admin/controller/extension/ocs/setup.php`, `scripts/build.mjs`, `custom/db/migrations/0009_ocmod_files.php`, tests, `docs/ocstore-internals.md`, `specs/README.md`.

## Risks
- Same code in DB and file applies twice -> migration 0009 plus REQ-OCM-010 test.
- Hosts with read-only `system/` -> precheck message.
