# PLAN — SPEC-050 Top Menu, Cart Badge, Cart Page, Subcategory Carousel, Sort Dropdown

## Approach

1. **Badge** — in `theme.js` (header counters block) also set `data-count` / `data-badge` on the button (`#cart > button`) next to the wrapper; keep the wrapper attributes for the existing CSS selector; a static test scans the stylesheet for `attr()` contents and checks the writer. Verified in the browser by adding and removing an item.
2. **Top menu** — pure `Ocs\TopMenu` (sanitise settings, build the view, active marker) in the `ocs` extension; theme form fieldset (checkbox list of information pages from `catalog/information` model in admin, sort inputs, per-language group title tabs); the view data is added to the header by the existing `ocs_theme_display` event (route `common/header`):
   information titles through the catalog `information` model, blog link through the blog module's menu route when it exists. `header.twig` renders the items in the utility bar; CSS with the `--sale` token and the scrollable row on phones.
3. **Cart page** — hand-written `checkout/cart.twig` (leaves the generator); a catalog view event on `checkout/cart` adds `old_price` / `old_total` per product (guarded); CSS for line cards, stepper, `<details>` panels, summary box; cart JS hooks and ids unchanged.
4. **Subcategories** — `ocs_listing` category event adds `thumb` per child (same `getCategories` call, matched by index); `category.twig` renders an `ocs-carousel` of mini cards; chips and refine lists removed; CSS for the cards.
5. **Sorting** — remove the `.ocs-sortchips` rows from the four listing templates and their CSS; confirm the toolbar select lists the extra sort options added by `ocs_listing`.
6. **Checks** — PHP unit tests for `Ocs\TopMenu`; targeted Node tests; browser checks of home, category, cart (empty and with a sale product), product at 360/768/1280 px; the class-name collision check from the SPEC-049 lesson.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Badge fix | write the attributes where the pseudo-element lives | `attr()` cannot read a parent's attribute |
| Top menu | theme setting + pure builder class | requirement: client controls the pages; testable without the store |
| Old cart price | view event, display only | the cart totals stay the core's |
| Subcategories | existing carousel component | one carousel implementation (SPEC-037) |

## Affected files / directories

- `custom/theme/ocs/template/common/header.twig`, `checkout/cart.twig`, `product/category.twig`, `product/search.twig`, `product/manufacturer_info.twig`, `product/special.twig`, `stylesheet/stylesheet.css`, `javascript/theme.js`
- `custom/extensions/ocs/**` (theme form, `TopMenu`, display event), `custom/extensions/ocs_listing/**` (category thumbs), a cart view event (in `ocs_ajax_cart` or `ocs_listing`), `scripts/gen-theme-templates.mjs`
- `scripts/test/php/TopMenuTest.php`, `scripts/test/topmenu-cart.test.mjs`

## Risks

- The blog module's route differs between installs -> read from its menu data; absent -> item hidden.
- Old price math with options -> clamp so the struck price is never lower than the current one.
- Removing chips changes the listing toolbar layout -> checked on category, search, special and manufacturer pages.
