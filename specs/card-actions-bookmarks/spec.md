# SPEC-044 — Card Actions, Cookie Bookmarks, Quick-View Removal, Stock/SKU Switch

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-26
**Refines:** SPEC-025 (quick order), SPEC-026 (AJAX cart), SPEC-037 (quick view), SPEC-035 (product page), SPEC-006 (theme settings)

## Problem

Review items 1-6:

1. Core `common.js` scrolls the page to the top after "Buy", "Compare" and "Wishlist" (`animate({scrollTop: 0})`). Wishlist/compare buttons give no
   visual feedback and the state is lost on reload.
2. After "Buy" the button does not change, so the visitor cannot tell the product is in the cart.
3. Quick view (SPEC-037) is not wanted.
4. Quick order exists on catalogue cards and module blocks; only the product page should have it, and it does not work now.
5. Wishlist needs a login (guests only get a session copy that is lost); it must work without login and keep its data in cookies.
6. The "in stock" label and the article number (SKU/model) cannot be switched off; they must be switchable in the theme settings and the switch must
   apply to every block, module, popup and list of the storefront.

## Scope

Theme `ocs` (`theme.js`, stylesheet, templates), extensions `ocs_quick_view` (removed), `ocs_quick_order` (product page only), a new
bookmarks service (wishlist + compare in cookies), theme admin settings form, `ocs_ajax_cart` / `ocs_listing` / `ocs_product_carousel` /
`ocs_ajax_search` / `ocs_product_extras` data providers (stock, model).

## Out of scope

Admin-side wishlist reports, moving bookmarks between devices, any change to core files.

## Functional requirements

### Card actions (items 1, 2)

- **REQ-CAR-001** — Buy, wishlist and compare actions never scroll the page (the core `scrollTop` animation is neutralised in the theme layer; no
  `core/` edit). Feedback is given in place.
- **REQ-CAR-002** — Wishlist and compare buttons have an "active" state (filled heart / compare icon, `aria-pressed="true"`, changed `title`).
  The state is set on click, restored on every page load (cards, product page, modules, carousels, AJAX-loaded cards) and cleared when the item
  is removed; a second click removes the item (toggle).
- **REQ-CAR-003** — After a successful "Buy" the button of that product changes icon and label to an "In cart" state (check icon, text from the
  language file, links to the cart). The state is restored on page load from the cart contents (server-provided product-id list), and removed
  when the product leaves the cart. Applies to cards, modules and the product page buttons.

### Quick view and quick order (items 3, 4)

- **REQ-QVR-001** — The `ocs_quick_view` extension, its button, popup, events, language files, settings, `theme.js` block and CSS are removed;
  a migration uninstalls the module and its settings; no remaining reference (test).
- **REQ-QOR-040** — The quick-order button is rendered only on the product page; catalogue cards, category/search/manufacturer/special pages
  and product modules do not show it (events `ocs_quick_order_{category,search,manufacturer,special}_before` removed or no longer expose it).
- **REQ-QOR-041** — The product-page quick order works end to end: popup opens, validation messages show, an order is created with the
  chosen quantity and options, the visitor sees the confirmation. Verified in the browser on the test store (also under SeoPro URLs).

### Bookmarks in cookies (item 5)

- **REQ-BKM-001** — Wishlist and compare work without login. The lists live in first-party cookies (`ocs_wish`, `ocs_cmp`: comma-separated product
  ids, 90 days, `SameSite=Lax`, capped at 100 / 4 ids respectively as in core compare). Only ids are stored.
- **REQ-BKM-002** — The wishlist page and compare page render from the cookie for guests (no login redirect); the header counters match.
  For a logged-in customer the cookie list is merged into the account wishlist at login and the cookie is kept in sync.
- **REQ-BKM-003** — Server input is validated: ids are cast to int, deduplicated, existing enabled products only; a damaged cookie is ignored.
  Cookies hold no personal data.

### Stock and SKU switches (item 6)

- **REQ-VIS-050** — The theme settings form (admin) has two switches, `theme_ocs_show_stock` and `theme_ocs_show_sku` (default on), per store.
- **REQ-VIS-051** — When a switch is off the corresponding element is absent from the output of **every** storefront surface: product page
  (info list, sticky bar), category/search/special/manufacturer cards, home blocks and module shelves (featured, latest, bestseller, special,
  carousel, viewed), AJAX search suggestions, cart panel and cart page, compare and wishlist tables, checkout confirmation, stickers that state
  stock, JSON endpoints. It is removed on the server (not hidden by CSS); a single helper reads the settings so new blocks cannot forget it.
  The out-of-stock purchase logic (pre-order / back-in-stock buttons) is unaffected.

## Non-functional requirements

- **REQ-CAR-020** — No new third-party library; ES5 in `theme.js`; no console errors; pages without JS keep core behaviour.
- **REQ-BKM-020** — Listing pages stay cacheable (no per-user markup; active states are set by JS).

## Edge cases

- Cookies disabled -> buttons still work for the page lifetime; no crash.
- Product removed/disabled while in a cookie -> dropped silently.
- Cart add with required options -> redirect to the product page as in core; no "In cart" state until success.
- Both switches off and the out-of-stock pre-order flow -> the flow works, the label is not shown.

## Open decisions (default in bold)

1. Item 1 says the compare button should light the heart: **heart = wishlist, compare icon gets its own active state** (both behave the same);
   tell me if the heart must light up on compare.
2. Compare also moves to cookies (**yes**, same service) or stays on the core session.
3. "In cart" button: **click opens the cart panel / cart page**; or a second click adds one more unit.
4. Stock/SKU switch scope: **one switch each, global per store**; or separate switches for listings and the product page.
5. Removing quick view: **uninstall by migration and delete the code**; or keep the code disabled.

## Implementation notes

- Defaults of the open decisions were used (approved 2026-10-26). `theme.js` budget raised from 60 to 68 KB (card actions replaced the removed quick view).
- Stock/SKU: one view event (`ocs_theme_display`, `catalog/view/*/before`, sort 99) strips `model`, `sku`, `stock`, `availability`, `ocs_stock` from the view data at every
  level and sets `ocs_show_stock` / `ocs_show_sku` for the table headers; AJAX search JSON is guarded in its controller. The return forms (`account/return*`) are excluded:
  their `model` is customer input. The out-of-stock *sticker* is a separate admin-defined sticker (SPEC-029); it is not removed by the switch (switch it off in the stickers module).
  The schema.org `sku` in JSON-LD (SEO) is not visible text and stays.
- Applied migrations are immutable: the quick-order listing events are removed by migration 0005, not by editing `ocs_quick_order/db/001_install.php`.
- Generated templates that now carry hand edits (cart, confirm, wishlist, order_info, compare) lost the `@generated` marker; `product/compare.twig` left the regenerate list.
- Quick order (REQ-QOR-041): on the rebuilt test store the product page popup opened, validated and created orders (plain product and a product with a required option, uk language).
  The failure from the review was not reproduced there; if it persists on the live site, send the browser console and the response of `extension/module/ocs_quick_order/submit`.
- Verified in the browser on the test store: no scroll after buy/wishlist/compare, active states after reload, "in cart" state on load and after buy, guest wishlist from the cookie.

## Acceptance criteria

- [x] Buy / wishlist / compare on a scrolled page do not move the scroll position (browser check).
- [x] Heart and compare icons are active after click and after reload; the "In cart" state shows after buy and after reload.
- [x] No quick-view button/popup/route/setting remains; quick order appears only on the product page and creates an order.
- [x] A guest adds to the wishlist, closes the tab, comes back: items are still there; counters correct.
- [x] With both switches off, a crawl of home, category, search, product, cart, compare, wishlist and the AJAX search JSON contains no stock/SKU text.
- [x] `npm test`, `npm run verify` green; package rebuilt.
