# PLAN — SPEC-044 Card Actions, Cookie Bookmarks, Quick-View Removal, Stock/SKU Switch

## Approach

1. **Card actions** — `theme.js` module `OCS.card`: replaces the core globals `cart.add`, `wishlist.add`, `compare.add` with theme versions that post
   to the same endpoints but do not scroll. Active / "in cart" classes are toggled by product id; the initial state comes from the cart ids
   (event in `ocs_ajax_cart`) and from the bookmark cookies read in JS. A MutationObserver re-applies state to AJAX-inserted cards.
2. **Quick view removal** — delete `custom/extensions/ocs_quick_view`, its `theme.js` block, CSS, `data-ocs-qv*` body attributes and the
   `.ocs-qv-add` reference in `OCS.out`; an idempotent migration removes the events, setting keys and the module row.
3. **Quick order** — drop the four listing events and the card/module markup (`card.twig`, module twigs); reproduce the product-page failure
   in the browser first, find the root cause, then fix it and add a test (if the cause changes the requirements: stop, update the spec).
4. **Bookmarks** — new extension `ocs_bookmarks`: a catalog event reads the cookies and hydrates `session['wishlist']` / `session['compare']`;
   JSON routes `extension/ocs_bookmarks/{add,remove}`; theme overrides of `account/wishlist` and `product/compare` render for guests; a
   login event merges the cookie list into `customer_wishlist`.
5. **Stock/SKU switches** — settings in the theme form with migration defaults; helper `system/library/ocs/display.php` (`show('stock'|'sku')`);
   every data provider and template guards on it.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Scroll removal | replace core globals in the theme | core is read-only; CSS cannot stop a JS scroll |
| Bookmark store | cookies + session hydration | required by the review; keeps core models working |
| Stock/SKU | server-side helper | requirement: absent from all output incl. JSON; CSS hiding rejected |

## Affected files / directories

- `custom/theme/ocs/javascript/theme.js`, `stylesheet/stylesheet.css`, `template/ocs/card.twig`, `template/product/*.twig`,
  `template/extension/module/*.twig`, `template/account/wishlist.twig`, `template/product/compare.twig`, `template/checkout/cart.twig`
- `custom/extensions/ocs_quick_view/` (deleted), `ocs_quick_order/`, new `ocs_bookmarks/`, `ocs_ajax_cart/`, `ocs_listing/`,
  `ocs_product_carousel/`, `ocs_ajax_search/`, `ocs_product_extras/`, `ocs/` (theme admin form, languages)
- `custom/db/migrations/` (2 new), `scripts/test/`, `scripts/browser/`

## Risks

- Replacing core JS globals breaks if core `common.js` changes -> core is pinned to 3.0.3.7; a test asserts the wrappers exist.
- A surface missed by the stock/SKU switch -> a test greps all templates and providers for `model` / `ocs_stock` outside the helper.
- Unknown cause of the quick-order failure -> reproduce first.
