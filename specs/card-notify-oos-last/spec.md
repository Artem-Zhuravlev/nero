# SPEC-120 — Товар не в наличии: колокольчик «сообщить о наличии» и такие товары в конце списка

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-29
**Refines:** SPEC-077 (bell on out-of-stock cards), SPEC-025 (back-in-stock), SPEC-034 (category listing)

## Problem

1. SPEC-077 added a bell button to out-of-stock cards, but `theme.js` (`labelNotify`) hides it whenever the header does not expose
   `data-ocs-notify`, i.e. when the setting `module_ocs_back_in_stock_status` is `0`. The install migration sets that setting to `0`, so on a
   fresh store the card shows the status "Немає в наявності" and price, and **no button at all**.
2. Out-of-stock products are mixed with available ones in category, brand, search and special listings; the customer has to scroll past
   unavailable products.

## Purpose

An out-of-stock card always has a bell that opens the "notify me" form, and out-of-stock products are listed after the available ones.

## Scope

- Theme `custom/theme/ocs`: `card.twig`, `theme.js` (`labelNotify`), stylesheet only if needed.
- Extension `ocs_back_in_stock`: header event, install/migration default of the module status.
- New OCMOD `custom/ocmod/ocs_listing_stock_last.ocmod.xml` on `catalog/model/catalog/product.php` (`getProducts`, `getProductSpecials`).
- Listings fed by those core methods: category, manufacturer, search, specials, ajax-search.

## Out of scope

Product page button (already works), notification mail flow, admin, `core/`, product modules with their own SQL (`ocs_product_carousel`,
featured/latest/bestseller) unless approved as an extra, "pre-order" products.

## Functional requirements

- **REQ-OOS2-001** — A card with `is-out` renders the bell button instead of the buy button whenever the module is on (now the default,
  REQ-OOS2-003). Title and `aria-label` come from the module string handed over by the header event (`labelNotify`); no extra fallback is
  needed (approach corrected during implementation: the root cause is the module default, not the JS).
- **REQ-OOS2-002** — Click on the bell opens the "notify me" form (`extension/module/ocs_back_in_stock/form`) for that product; the card does
  not navigate.
- **REQ-OOS2-003** — The module `ocs_back_in_stock` is enabled by default: a numbered migration sets `module_ocs_back_in_stock_status = 1`
  (the install migration's default changes to `1` for new installs). An admin can still switch it off; then the bell is hidden (SPEC-077 REQ-OOS-003).
- **REQ-OOS2-004** — In every listing built by `getProducts()` and `getProductSpecials()`, products with `quantity <= 0` come **after** all
  others, whatever the chosen sort (default, name, price, rating, popular, date). Inside each group the chosen sort is kept.
- **REQ-OOS2-005** — Pagination stays consistent: the ordering is applied in SQL (before `LIMIT`), so page 1 holds available products and
  out-of-stock ones appear on the last pages.

## Non-functional requirements

- **REQ-OOS2-006** — OCMOD (not a core edit), documented header with the reason an event is insufficient; one extra ORDER BY expression, no
  extra query or join.
- **REQ-OOS2-007** — `npm run verify` and `npm test` green; theme archive rebuilt (`npm run pack:theme`).

## Data requirements

Setting `module_ocs_back_in_stock_status` (migration, idempotent). No new tables.

## Interfaces

`product.ocs_notify` / `body[data-ocs-notify]` (form URL + label) are kept; OCMOD code `ocs_listing_stock_last`.

## Edge cases

- Module switched off by admin -> no bell, card unchanged (SPEC-077 REQ-OOS-003).
- All products of a category are out of stock -> normal order among themselves.
- Product in stock but all options out of stock -> not treated as out of stock (cards have no options; same as SPEC-077).
- Sort by price/rating with out-of-stock products cheaper -> still after available ones.

## Acceptance criteria

- [ ] "Kiro Pulse 10 Pro" card (status "Немає в наявності") shows a bell where the buy button would be, on a fresh store.
- [ ] Click on the bell opens the notify form; the card does not open the product page.
- [ ] Category listing sorted by price asc: all in-stock products first, then out-of-stock ones, each group ascending.
- [ ] Page 1 of a paginated category contains no out-of-stock product while in-stock ones remain on later pages.
- [ ] Admin disables the module: no bell on cards.
