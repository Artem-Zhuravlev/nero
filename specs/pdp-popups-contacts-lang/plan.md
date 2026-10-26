# PLAN — SPEC-049 Product Code, Review/Question Popups, Contact Icons, Inline Language Switch

## Approach

1. **Code pill** — markup in the `product.twig` info column (`.ocs-code-pill`, `data-ocs-copy`), a small handler in `theme.js` (clipboard + fallback + live region); gated by `ocs_show_sku`.
2. **Popups** — the two forms move into hidden containers; the section buttons and the rating link call `OCS.popup(boxId)`, which moves the container content into `OCS.modal` and back on close (ids stay unique, core review JS keeps its references).
   The review form gets `pros` / `cons` inputs inside the existing `#form-review`, so the core `serialize()` posts them; a theme hook closes the popup after the success message.
3. **Pros/cons storage** — migration `003_review_extra`; catalog event `catalog/model/catalog/review/addReview/after` saves them, a view event on `product/review` adds them to each review (one guarded `IN()` query by review ids);
   admin view event shows them on the review form, a delete event cleans up.
4. **Contacts** — new row markup in `header.twig` and `footer.twig`, brand tokens and chip CSS; the data (`icon`, `key`, `title`, `href`) already exists from SPEC-047, the phone row keeps number and label.
5. **Language** — `language.twig` rewritten as an inline list inside the same form; the three-letter label is cut in the template (Twig `slice` is UTF-8 safe).
6. **Checks** — PHP tests for the extra-table handlers with the table missing and present; template tests; a behavioural smoke of the product page on a migrated and an un-migrated database; browser checks at 360/768/1280 px.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Review extras | own table + after-event | core files are read-only; no change to the core controller |
| Popup content | move DOM nodes | keeps ids and the core review script unchanged |
| Code | product model | already the SKU the switch controls |

## Affected files / directories

- `custom/theme/ocs/template/product/product.twig`, `product/review.twig`, `common/header.twig`, `common/footer.twig`, `common/language.twig`, `stylesheet/stylesheet.css`, `javascript/theme.js`
- `custom/extensions/ocs_product_extras/**` (db/003, catalog + admin events, model, languages), `scripts/gen-theme-templates.mjs` (language and review templates leave the regenerate list)
- `scripts/test/php/ReviewExtraTest.php`, `scripts/test/pdp-popups.test.mjs`

## Risks

- Moving the review form in the DOM breaks core handlers bound by id -> the container is moved back on close and clicks are resolved at click time (verified in the browser).
- The `addReview` after-event also runs for admin/imports -> it acts only on a storefront request that carries the posted fields.
- The core language JS (`.language-select`) -> the markup keeps the class and the form id.
