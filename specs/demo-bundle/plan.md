# PLAN — SPEC-040 Installable Theme Package

## Approach

`scripts/pack-theme.mjs` walks `custom/theme/ocs` (mapped to `upload/catalog/view/theme/ocs/`) and each
`custom/extensions/*/upload`, fails on path collisions or paths outside the core installer's allow-list, merges the OCMOD XMLs,
copies `scripts/lib/php/Migrate` + the needed migrations into `upload/system/library/ocs/setup/`, adds a thin admin controller
`extension/ocs/setup` and writes the zip. The setup controller builds the existing `Ocs\Migrate` runner on top of the store's `$this->db`
(`CoreDb` adapter already exists), runs pending migrations, refreshes OCMOD and renders a result page.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Distribution | one `.ocmod.zip` via Extensions -> Installer | Native OpenCart mechanism; no full deployment needed |
| DB step | admin "Setup" route running our migration runner | This core's installer has no `install.sql/php` step (only `upload/` + `install.xml`); stays one code path with `db:migrate` |
| Activation | explicit tick, not automatic | Installing must never change a live storefront |
| Demo data | separate opt-in action | Keeps package safe for real shops |
| Full-stand zip with bundled PHP/MariaDB | dropped | User decision: theme is a module, not a deployment |

## Affected files / directories

- `scripts/pack-theme.mjs`, `scripts/test/pack-theme.test.mjs`, `package.json`, `.gitignore` (`dist/`)
- `custom/extensions/ocs/upload/admin/controller/extension/ocs/setup.php` (+ language, twig), package manifest `custom/VERSION`
- `docs/theme-install.md` (+ copy as `README-INSTALL.md` in the zip), `specs/README.md`

## Risks

- Migration classes assume CLI context -> adapter layer and tests with the SQLite-backed PHP runner, then a real MariaDB run.
- Allow-list excludes something the theme needs (e.g. `image/` fonts) -> all assets live under `catalog/view/theme/ocs/`.
- Some modules need tables/features absent in vanilla OpenCart -> pre-check, documented.
- The local MariaDB/PHP under `var/tools/` is needed to verify on a clean store; start it only with the user's OK.
