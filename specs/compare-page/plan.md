# PLAN — SPEC-057 Compare Page

## Approach

Everything stays in `ocs_bookmarks` (owner of the compare cookie) and the `ocs` theme; core is untouched.

1. **Pure helpers** in `Ocs\Bookmarks`: `groups($ids, $categoryOf)` and `trim($ids, $categoryOf, $total, $perCat)`; unit-testable.
2. **Model** `extension/ocs_bookmarks/compare`: product→deepest category (join `category_path`) and category names, both batched.
3. **Controller event** `catalog/controller/product/compare/before` (after `hydrate`): reads `?category`, narrows `session['compare']`
   to that group so the core controller builds only those products. **View event** `catalog/view/product/compare/before`:
   adds tabs, the active id and a `same` flag per characteristic row, computed on the final product list.
4. **Template** `compare.twig` rewritten: tabs, switch, `.ocs-cmp-scroll` wrapper with sticky header and first column, collapsible groups, `data-same` on identical rows.
5. **JS** (`theme.js`): the switch toggles a class on the table and persists to `localStorage`; remove goes through the cookie endpoint, then re-renders.
6. **Cookie limits**: the `hydrate` event applies total/per-category trimming (only when the list is longer than 4) and rewrites the cookie. The remove link is handled in the filter event so the customer returns to the same tab.
7. **Icon**: new balance-scale `<symbol id="i-compare">` in `sprite.twig`; usages unchanged.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Compare category | deepest category of the product | Deterministic, no admin setting. A dedicated "compare group" field is more admin work; deferred. |
| Filtering place | narrow `session['compare']` in a before-event | Reuses the core data builder (prices, attributes, stock) without OCMOD; the cookie is untouched so other tabs keep their products. |
| Differences only | client-side toggle on server-computed `data-same` | Instant, no reload, no JS = full table. A `?diff=1` route state was rejected. |
| Limits | 12 total / 4 per category | The old flat limit of 4 would make categories pointless. |

## Affected files / directories

- `custom/extensions/ocs_bookmarks/` — library, model, events, languages (uk-ua, ru-ru, en-gb), `extension.json`, migration `002` (event rows)
- `custom/theme/ocs/template/product/compare.twig`, `template/ocs/sprite.twig`
- `custom/theme/ocs/stylesheet/stylesheet.css`, `javascript/theme.js`
- `scripts/test/` and PHP tests for grouping/trimming; traceability
- `specs/README.md`; rebuild `dist/ocs-theme-<VERSION>.ocmod.zip`

## Risks

- The core remove link (`product/compare&remove=`) changes the session only, not the cookie -> the remove button goes through the cookie endpoint; verify during implementation.
- `category_path` can be empty for categories created without repair -> fall back to the product's lowest `category_id`.
- Sticky columns inside an overflow wrapper need care (sticky `th` in the scroll box); test on iOS Safari.
