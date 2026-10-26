# PLAN — SPEC-042 SVG-Only Icons, Stars, Layer Scale, Bottom Bar

## Approach

1. Inventory: list every `fa` use (done: ~55 occurrences in 20 files) and map each to a sprite symbol; extend `sprite.twig`.
2. Replace markup file by file; strings built in `theme.js` use a helper `OCS.icon(name)` that returns the `<svg><use>` string.
3. `CategoryTree::icon()` gets a `fa-*` -> symbol map with a neutral fallback.
4. Stars: new `.ocs-rating` component (CSS + one Twig macro-like include) replacing three `fa-stack` blocks and the review form stars.
5. z-index: add the token scale, replace every literal by a token, fix stacking contexts that trap dropdowns (`overflow: hidden`,
   `contain`, `container-type` on ancestors), add the overlap check script run against the test store.
6. Bottom bar: rework `footer.twig` block + `.ocs-tabbar` CSS; hide under modals (`body.ocs-modal-open`).
7. Tests (static: no `fa`, sprite symbols exist, no z-index literals) + browser overlap and star alignment checks on the test store.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Icon delivery | Inline `<svg><use>` from the existing sprite | Already used in the header; no font, no extra request |
| Stars | SVG row with CSS, no icon font stack | `fa-stack` positioning is what drifts |
| Category icon names | Keep `fa-*` in DB, map at render | No migration, admins keep their values |
| z-index | Token scale + test | Literals drift again otherwise |
| Core fallback pages | Keep FA stylesheet for them only (Open decision 1) | Overriding checkout/account is a separate large job |

## Affected files

`custom/theme/ocs/template/{common,ocs,product}/*.twig`, `stylesheet/stylesheet.css`, `javascript/theme.js`,
`custom/extensions/ocs_{ajax_cart,benefits,category_menu,city,home_slider,mega_menu,quick_view}/.../*.twig`,
`custom/extensions/ocs_menu/upload/system/library/ocs/categorytree.php`, `scripts/test/theme.test.mjs`, `scripts/check-layers.mjs` (new).

## Risks

- Mapping misses an icon name -> neutral fallback + test lists unmapped names found in templates.
- Replacing the FA font visually changes many small icons -> screenshot review per page.
- z-index refactor can reorder overlays -> overlap check on every popup, plus manual review of the drop-down screenshots.
