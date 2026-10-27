# PLAN — SPEC-052 Remove the Cities Feature

## Approach

Mirror SPEC-044: one migration unregisters everything and drops tables, then the code, template hooks, JS/CSS blocks, demo seed and
tests of the feature are deleted. `custom/extensions/ocs_city/` is removed as a whole; `ocs_request` keeps its core address fields.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Scope | Delete whole extension | Stores page, tracking and delivery block depend on the city model; keeping them half-working is worse. Alternative (hide only the header button) rejected: leaves dead code. |
| Data | Drop tables in migration | The user asked to remove the feature; no consumers remain. |

## Affected files / directories

- `custom/extensions/ocs_city/` (delete), `custom/db/migrations/0008_remove_cities.php` (new)
- `custom/theme/ocs/template/common/header.twig`, `product/product.twig`, `javascript/theme.js`, `stylesheet/stylesheet.css`, `DESIGN.md`
- `custom/extensions/ocs/.../setup.php` (language string), `scripts/lib/php/Setup/DemoCatalogue.php`, `custom/catalog/demo/catalogue.json`
- `scripts/test/theme.test.mjs`, `scripts/test/php/CityTest.php`, `specs/README.md`, `specs/cities-delivery-stores/spec.md` (status superseded)

## Risks

- Other code referencing `ocs_city_block` / `ocs_city` template vars -> grep after removal, run verify.
- Dropping tables is irreversible -> stated in spec; migration idempotent.
