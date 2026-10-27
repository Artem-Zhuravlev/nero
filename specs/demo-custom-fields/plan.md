# PLAN — SPEC-068 Demo Data Covers Every Custom Field

## Approach

Keep the two-stage flow. `catalogue.json` gets the new per-product/review/category data plus `settings` and `records` sections. `DemoCatalogue`
gets a `customFields()` step after products exist: videos, questions, review extras, sticker links, menu banners, discounts, then `settings()` and
`records()`. Setting writes go through one helper that skips non-empty values and registers `setting:<code>:<key>` with a hash of the written value,
so removal clears only untouched demo values. `DemoSeed` keeps the home-block logic and uses the same helper for module switches.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Where the data lives | `catalogue.json` | Already packaged and human-editable; a second file adds packer work |
| Overwrite policy | only empty values | The owner's data must survive (REQ-DCF-004) |
| Removal of settings | clear if value still equals the demo value | Avoids deleting the owner's edits |
| New button | none | The request is to extend the existing button |

## Affected files / directories

- `custom/catalog/demo/catalogue.json`, `scripts/lib/php/Setup/DemoCatalogue.php`, `DemoSeed.php`, `Service.php`
- `scripts/lib/demo-images.mjs` (menu banner images if new), `scripts/test/php/DemoCatalogueTest.php`
- README-INSTALL, `specs/README.md`, `dist/` archive via `npm run pack:theme`

## Risks

- Column drift of custom tables -> tests create the real DDL in SQLite fixtures.
- Setting formats differ per module -> reuse each module's sanitizer (`Contacts::sanitize`, `InfoBlocks::sanitize`, `TopMenu::sanitize`, `Credit::cleanPlans`).
