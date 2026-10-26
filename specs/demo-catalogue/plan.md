# PLAN — SPEC-041 Demo Catalogue

## Approach

A JSON data file describes categories, manufacturers, attributes, options and products with per-language texts and image
slugs. `scripts/gen-demo-images.mjs` renders deterministic placeholder images (flat colour + simple glyph) with a
dependency-free PNG writer; the packer calls it and adds the files. `Ocs\Setup\DemoCatalogue` receives the admin models from the
Setup controller through a loader callable (so it stays unit-testable with fakes), creates entities in dependency order
(attribute groups, attributes, options, manufacturers, categories, products, reviews, information), records each id in
`ocs_demo_catalogue`, then fills OCS tables. Removal walks the registry in reverse order.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Write path | core admin models | Handles SEO URLs, multi-language rows, store links; raw SQL would drift from the core schema |
| Images | generated placeholders in the package | No licence issues, no network, tiny |
| Tracking | id registry setting + `OCSDEMO` marker | Exact, reversible removal |
| Languages | texts for uk/ru/en applied to installed languages | Works for any language set |

## Affected files

`custom/catalog/demo/catalogue.json`, `scripts/gen-demo-images.mjs`, `scripts/lib/php/Setup/DemoCatalogue.php`,
`scripts/lib/pack-theme.mjs`, Setup controller/twig/language, `docs/theme-install.md`, tests, `specs/README.md`.

## Risks

- Admin models need request/session context (user, language) -> run only inside the admin request; fakes in unit tests, e2e on the test store.
- Execution time -> chunk by entity type via AJAX steps if over budget.
- Core model signatures differ slightly between ocStore and OpenCart -> pinned to 3.0.3.x and covered by e2e.
