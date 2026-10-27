# SPEC-056 — Default Product Modules in the Site Card Style

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-034 (listing cards), SPEC-037 (carousels), SPEC-039 (visual identity), SPEC-044 (card actions)

## Problem

The core product modules (`featured`, `special`, `latest`, `bestseller`, `featured_product`) are overridden in
`custom/theme/ocs/template/extension/module/` but still print the old OpenCart card (`.product-thumb` with a
`.button-group` of cart/wishlist/compare buttons, no stickers, no stock, no code, no reviews count). Next to the
category listing and the home carousels they look foreign. Their data also lacks what the shared card needs
(`model`, `reviews`, `discount_pct`, `stickers`, `ocs_stock`), because the enrichment events exist only for the
category, search, manufacturer and special *pages*, not for modules.

## Purpose

Every default module that lists products renders the same card as the rest of the site (`ocs/card.twig`),
with the same data, in a layout that works in the content area and in the side columns.

## Scope

- Templates `featured`, `special`, `latest`, `bestseller`, `featured_product` (theme `ocs`) use `ocs/template/ocs/card.twig`.
- View events on `catalog/view/extension/module/<name>/before` for these five modules adding the card data.
- CSS for the module grid (content area: container-query grid; `#column-left` / `#column-right`: one column).

## Out of scope

- Modules that already use the shared card (`ocs_product_carousel`, `ocs_viewed`, accessories/similar on the product page).
- Blog modules (`blog_featured`, `blog_latest`, `featured_article`): they list articles, not products.
- Admin screens and module settings (limit, image size, product selection stay as they are).
- Core files (`core/` stays read-only; changes go through theme overrides and events).

## Functional requirements

- **REQ-MPC-001** — The five module templates render each product with `{% include 'ocs/template/ocs/card.twig' %}`;
  the old `.button-group` markup is removed from them. The heading stays `h2.ocs-sec-title`; an empty product list renders nothing.
- **REQ-MPC-002** — The card data of module products matches the listing pages: `model`, `quantity`, `reviews`,
  `discount_pct` (ocs_listing), `stickers` (ocs_stickers), `ocs_stock` (ocs_ajax_cart), each through its own extension
  by an event on `catalog/view/extension/module/<name>/before`. A disabled extension simply leaves its keys out.
- **REQ-MPC-003** — Module cards are laid out in a grid: columns are chosen from the container width
  (`repeat(auto-fill, minmax(...))`, container queries as in SPEC-038), not from Bootstrap `col-*` classes; in
  `#column-left` / `#column-right` the grid is one column.
- **REQ-MPC-004** — Cards in modules behave like cards elsewhere: wishlist/compare tools, add-to-cart with `minimum`,
  stickers, video badge, hover state; no horizontal overflow at 320px.
- **REQ-MPC-005** — Missing card keys (extension disabled, other theme data) are guarded in the template and still
  render a valid card; event handlers never throw to the customer (REQ-EXT-062).

## Non-functional requirements

- **REQ-MPC-010** — No extra DB queries per module beyond one batched query per extension (as on listing pages).
- **REQ-MPC-011** — CSS of the old module card is removed only if nothing else references it
  (`.product-thumb .button-group` stays while blog templates use it).

## Data requirements

New event rows (migration `002` in `ocs_listing`, `ocs_stickers`, `ocs_ajax_cart`), idempotent. No schema or settings changes.

## Interfaces

Events: `catalog/view/extension/module/{featured,special,latest,bestseller,featured_product}/before`
-> `extension/ocs_listing/events/beforeModule`, `extension/ocs_stickers/events/beforeModule`,
`extension/ocs_ajax_cart/events/beforeModule` (sort order keeps ocs_listing first, as on the listing pages).

## Edge cases

- Customer prices hidden (`config_customer_price`) -> `product.price` is false, card shows no price (existing card behaviour).
- Product without image -> core placeholder from the controller, unchanged.
- Module placed in a side column -> one column, card keeps its proportions.
- `featured_product` is absent in plain OpenCart -> its event is registered anyway and is harmless.

## Acceptance criteria

- [ ] On a page with all five modules in the content area the cards look identical to the category listing cards.
- [ ] The same modules in the left column show one card per row.
- [ ] Stickers, stock label, code and review count appear in module cards when the extensions are enabled.
- [ ] `npm run verify` is green and `dist/ocs-theme-<VERSION>.ocmod.zip` is rebuilt.
