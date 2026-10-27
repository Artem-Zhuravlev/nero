# SPEC-077 — Out-of-stock Cards: Bell Button, Grey Image, Price Size, Even Spacing

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-025 (back-in-stock), SPEC-034 (category listing), SPEC-056 (product cards in modules), SPEC-039 (visual identity)

## Problem

1. An out-of-stock product card still shows the round "add to cart" button; the customer cannot ask to be told when it returns.
2. Out-of-stock cards look like normal ones (only a small label tells the difference).
3. The price is large (up to 1.5rem, nowrap): for big amounts ("17400.00 грн.") it pushes the round button out of the card.
4. Vertical/horizontal spacing inside a card is uneven (tight gaps around the code, status, price row).

## Purpose

Out-of-stock cards are visibly "inactive" and offer "notify me" instead of "buy"; the price never breaks the layout; spacing is regular.

## Scope

Theme `custom/theme/ocs` (`template/ocs/card.twig`, `stylesheet.css`, sprite icon `i-bell`, `theme.js` only if needed) and the `ocs_back_in_stock`
extension (a view event that exposes the subscription form URL to listing cards). All product blocks that render `ocs/card.twig` (category, search,
brand, specials, modules, viewed, wishlist, home blocks).

## Out of scope

The product page (already has its own subscribe button), the notification e-mail flow, `core/`, admin.

## Functional requirements

- **REQ-OOS-001** — A card whose product is out of stock (`product.ocs_stock.state == 'out'`) and where back-in-stock is enabled shows a round **bell**
  button (same size/position as the cart button) instead of the cart button. Title/`aria-label` = the module's `button_subscribe` string.
- **REQ-OOS-002** — Click on the bell opens the existing "notify me" form (`extension/module/ocs_back_in_stock/form`, `OCS.req.open`) with the
  product id; the card does not navigate.
- **REQ-OOS-003** — If back-in-stock is disabled, an out-of-stock card shows no buy button (no dead cart button).
- **REQ-OOS-004** — The product image of an out-of-stock card has `filter: grayscale(.8)` in every product block (single class
  `.is-out` on `.product-thumb`, set in `card.twig`).
- **REQ-OOS-005** — The card price font size is **18px** (`1.125rem`) at every width, including container-query variants; price block may wrap,
  the round button keeps its size and never leaves the card (`min-width: 0` on price, `flex: none` on button).
- **REQ-OOS-006** — Even spacing in the card: one gap token (`--sp-3`) between caption items, equal inline padding (`--sp-4`) for image, caption
  and buy row, equal bottom padding; the buy row is aligned to the card bottom in all cards of a row.

## Non-functional requirements

- **REQ-OOS-007** — No new libraries; icon `i-bell` added to the SVG sprite; CSS uses existing tokens; styles added in the existing `@layer`s.
- **REQ-OOS-008** — `npm run verify` and `npm test` green; theme archive rebuilt (`npm run pack:theme`); `custom/VERSION` bumped as for earlier specs.

## Interfaces

`product.ocs_notify` = `{text, form}` added to listing products by `ocs_back_in_stock` view events (same triggers as `ocs_ajax_cart` labels);
CSS class `.is-out`; button `.ocs-cart-btn.ocs-notify-btn.ocs-req-btn` with `data-form`, `data-product-id`, `data-collect="1"`.

## Edge cases

- Product in stock but a chosen option out of stock: not handled on cards (no options there).
- Wishlist cards (with remove tool): bell still shown for out-of-stock products.
- Already in cart then out of stock: bell wins (stock state is authoritative).
- Very long price: wraps, button stays inside.

## Acceptance criteria

- [ ] Out-of-stock card (e.g. "Aurex Vision 14 Max"): bell instead of cart, image greyed, click opens the notify form.
- [ ] In-stock cards are unchanged except spacing and 18px price.
- [ ] Price "17400.00 грн." fits next to the button at 2, 3 and 4 columns and on a 375px phone.
- [ ] Back-in-stock module off: no button on out-of-stock cards.
