# SPEC-026 — AJAX Cart and Availability States

**Status:** in-progress
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-23
**Refines:** SPEC-024 REQ-MOY-013, REQ-MOY-023 · **Brief:** module 3 · **Depends on:** SPEC-023, SPEC-011

## Problem

Core adds to cart over AJAX but shows a small alert and a dropdown; the reference opens a cart panel, edits quantities in place and keeps header counters and totals current.

## Purpose

A cart side panel/popup opened after add, with editing, and consistent availability labels on cards and product page.

## Scope

Extension `ocs_ajax_cart`: add/update/remove endpoints returning JSON, panel in `OCS.modal` (side variant), header counter
and total refresh, availability text. Reuses core `checkout/cart` model.

## Out of scope

Checkout, coupons/vouchers in the panel (link to full cart), wishlist/compare behaviour.

## Functional requirements

- **REQ-CRT-001** — Adding a product (card, category, product page, quick view) does not reload the page and opens the
  cart panel with the added line highlighted.
- **REQ-CRT-002** — Panel lists lines (image, name, options, unit price, quantity stepper, line total, remove), totals
  and buttons "Continue" and "Checkout" (visible at all times).
- **REQ-CRT-003** — Quantity change and removal update the server cart and totals; all prices are calculated server-side.
- **REQ-CRT-004** — Same product with different options is separate lines; required options missing -> field errors, panel not opened.
- **REQ-CRT-005** — Header cart counter and total update after every cart change without reload; also on other tabs
  after next focus.
- **REQ-CRT-006** — Errors (stock minimum, out of stock, option missing) and success messages are shown inside the panel/popup.
- **REQ-CRT-010** — Availability label on card and product page: "In stock", "n left" below a configurable threshold,
  "Out of stock" (hands over to SPEC-025 pre-order buttons).

## Non-functional requirements

- **REQ-CRT-020** — Panel opens < 300 ms after server response; works with keyboard (focus trap, Esc, SPEC-023 REQ-DSG-013).
- **REQ-CRT-021** — With the module off, core cart behaviour is untouched (REQ-MOY-001). uk/ru/en; PHP 7.4.

## Data requirements

Settings `module_ocs_ajax_cart_*` only.

## Edge cases

- Session expired -> endpoint returns empty cart state, panel shows empty message.
- Price changed since page load -> panel shows current price.

## Acceptance criteria

- [ ] Add, change quantity, remove work without reload and match the full cart page totals.
- [ ] Counter and total in header match the panel.
