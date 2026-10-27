# SPEC-070 — "Already in cart" Button State

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-044 (card actions, "in cart" button), SPEC-006 (theme)

## Problem

After a product is added, its buttons already turn green with a check (SPEC-044). The state is not clear enough: the wide button on the product
page (`product.twig`) says "In cart", which reads as a label, not as a result, and the check on round card buttons is a plain static stroke.

## Purpose

The customer sees at once that the product is already in the cart: a nicer animated check on round icon buttons, and the text "Уже в корзине" on the
product page button.

## Scope

Theme `custom/theme/ocs` (`stylesheet.css`, `theme.js`) and the strings of `custom/extensions/ocs_bookmarks` (language files + `events.php`). Storefront only.

## Out of scope

Removing a product from the cart through the button, quantity changes, `core/`, admin.

## Functional requirements

- **REQ-INC-001** — A round icon cart button (`.ocs-cart-btn`, cards, modules, blog article) that holds a product already in the cart shows a
  check icon instead of the cart icon, with the success colour (existing behaviour kept).
- **REQ-INC-002** — The check is drawn with a short animation (stroke draw, ~0.35 s) with a slightly thicker stroke and rounded caps, and a small
  "pop" of the button when the state is set after a click. No animation on page load or with `prefers-reduced-motion`.
- **REQ-INC-003** — A wide text cart button (`#button-cart` on `product.twig`, `data-ocs-buy`) shows the check plus the text "Уже в корзине"
  (uk: "Вже в кошику", en: "Already in cart") from the `ocs_bookmarks` language files, new key `text_in_cart_btn`.
- **REQ-INC-004** — The tooltip/`aria-label` of round buttons keeps the short text `text_in_cart` ("В корзине").
- **REQ-INC-005** — The state is set after a successful `checkout/cart/add` and on page load for products already in the cart (existing mechanism);
  the original label returns if the product leaves the cart.

## Non-functional requirements

- **REQ-INC-006** — No new libraries; uses the sprite icon `#i-check`. CSS uses existing tokens.
- **REQ-INC-007** — `npm run verify` and `npm test` stay green; theme archive rebuilt (`npm run pack:theme`).

## Interfaces

`cfg.t.in_cart_btn` (JSON in `data-ocs-bk-t`), CSS class `.is-in-cart`, new class `.is-just-added` for the one-off animation.

## Edge cases

- Product with required options: the button turns only on a successful add.
- Button text longer on small screens -> wide button wraps/ellipsis, never overflows.
- Missing translation -> falls back to `text_in_cart`.

## Acceptance criteria

- [ ] Click on the round cart button of a card: the check animates, button is green; reload keeps it green.
- [ ] Product page button reads "Уже в корзине" with a check after adding and after reload.
- [ ] Reduced-motion: no animation, same final state.
