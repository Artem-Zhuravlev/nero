# SPEC-064 — Wishlist Page as Product Cards

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-006 (theme), SPEC-044 (bookmarks), SPEC-063 (account area)

## Problem

`account/wishlist` (served by `ocs_bookmarks`) still renders core's table (image, name, price, action). Everywhere else products are shown as cards, so the page looks foreign.

## Functional requirements

- **REQ-WLC-001** — `account/wishlist.twig` renders products in the same markup as the category listing: a `.ocs-cards` grid with `ocs/template/ocs/card.twig` per product. The table is removed.
- **REQ-WLC-002** — The card uses the catalogue image size (`image_product_*`), and the controller supplies the fields the card reads (description, rating, reviews, tax, discount percent); fields not available render nothing.
- **REQ-WLC-003** — On a wishlist card the heart tool is replaced by a remove control (`a[data-ocs-wish-remove]`, href `product.remove`) that is always visible. The cart button, compare tool and price behave as in the category card.
- **REQ-WLC-004** — Remove keeps working with and without JS (server link plus cookie sync from `theme.js`). The empty state and the "Continue" button are unchanged.

## Out of scope

Sort/limit controls, pagination, list/grid toggle on this page; the compare page.

## Acceptance criteria

- [x] `/index.php?route=account/wishlist` shows cards identical in look to a category grid, 2 per row at 375px.
- [x] Removing a card works; the cart button adds the product.
- [x] Category, module and compare pages are unchanged.
