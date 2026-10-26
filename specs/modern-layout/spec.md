# SPEC-038 — Modern Layout: Grid, Subgrid, Container Queries (moyo.ua as reference)

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-23
**Refines:** SPEC-023 (design), SPEC-024 (reference), SPEC-031, SPEC-033, SPEC-034, SPEC-035 · **Source:** brief "максимально современно: grid, subgrid, container queries etc, дизайн по аналогии с moyo"

## Problem

The theme is built on the Bootstrap 3 float grid (`col-*`, `row`, `product-layout`) with design tokens on top. It works, but it looks
like a re-skinned 2014 template: the header is two crowded rows, cards do not line up across a row, the home page is a stack of
full-width blocks, the product page is the core two-column float layout. The reference (moyo.ua) is dense, structured and
consistent from 320 px to wide desktops.

## Purpose

Rebuild the layout layer of the `ocs` theme on modern CSS (cascade layers, grid, subgrid, container queries, `:has()`, fluid
type) and bring home, category and product pages to the reference's structure, without changing functionality or core JS hooks.

## Scope

`custom/theme/ocs/stylesheet/stylesheet.css` (re-organised into layers), the theme overrides (`common/header`, `common/footer`,
`product/category`, `product/product`, `product/search|special|manufacturer_info`, `ocs/card`), the home blocks' templates and the
extension templates that carry layout classes. Core hooks (ids and classes used by core JS, SPEC-006 REQ-THM-013) stay.

## Out of scope

New features, a font download (Inter stays optional), admin pages,
OCFilter/Simple markup.

## Functional requirements

- **REQ-LAY-001** — Cascade layers: `@layer reset, tokens, legacy, base, layout, components, utilities, overrides;` (`legacy` holds the sections written before this spec and shrinks over time). Bootstrap 3 and Swiper CSS are
  imported into the lowest layer (or wrapped by `layer()` import) so theme rules win without `!important`; existing `!important`
  occurrences are removed except the `[hidden]` guards.
- **REQ-LAY-002** — Page shell as a grid: `body` is a grid (`auto auto 1fr auto`, one more row for the announcement bar) around the `.ocs-page` wrapper; `.container` is a CSS-grid track system
  (`max-inline-size` fluid via `clamp`, inline padding by `--gutter`), a 12-column fallback is not kept.
- **REQ-LAY-003** — Header: one compact main row (logo, catalogue button, search that takes the free space, account / compare /
  wishlist / cart icons with counters) above a slim utility strip (city, phones, links, language/currency); grid areas change at
  container widths, sticky with `backdrop-filter`; mobile keeps the bottom tab bar (REQ-MOY-024).
- **REQ-LAY-004** — Product listing: `display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 15rem), 1fr))`;
  cards are grid items with `grid-row: span 4; grid-template-rows: subgrid`, so image, title, price and buttons align across a
  row; list view is the same card in a wide container.
- **REQ-LAY-005** — Card as a container (`container-type: inline-size; container-name: card`): `@container card (min-width: …)`
  switches the card between compact (grid), roomy and horizontal (list) layouts; the same card works in listings, carousels, quick
  view and search.
- **REQ-LAY-006** — Home page grid: hero slider and two promo tiles side by side (`grid-template-areas`), a category-icons row,
  product shelves, brand strip, benefits strip (delivery, warranty, returns, support) and the newsletter block as a full-bleed
  band; areas collapse in a container query, not a media query.
- **REQ-LAY-007** — Category page: sidebar (categories + filter slot for OCFilter) and content as a two-track grid that collapses
  below a container width; sticky toolbar (count, sort, view, load more) using `position: sticky` and `:has()` for the
  active-filters state.
- **REQ-LAY-008** — Product page: two-column grid (sticky gallery | info), the buy box is a sticky card in the info column,
  sections are full-width grid rows with the section bar (SPEC-035) as their sticky navigation; options, delivery, credit and
  trade-in blocks are grid children that reflow by container width.
- **REQ-LAY-009** — Footer: grid of link groups with `subgrid` for aligned headings and lists, payment / social / newsletter areas,
  collapses to accordions (`<details>`) on narrow containers.
- **REQ-LAY-010** — Fluid system: type and spacing scale with `clamp()`; `aspect-ratio` on every image slot (no layout shift);
  `text-wrap: balance` on headings, `pretty` on text; logical properties (`inline`/`block`) in new rules; `color-mix()` for hover and
  surface states; `dvh` for full-height popups.
- **REQ-LAY-011** — Modals and popups (quick view, cart panel, request forms, city selector) are grid layouts whose columns follow
  the dialog width via container queries.
- **REQ-LAY-012** — Progressive enhancement: every modern feature sits behind `@supports` (subgrid, container queries, `:has()`);
  without them the page is a usable single-column / auto-fill grid; no JS is needed for layout.

## Non-functional requirements

- **REQ-LAY-020** — No horizontal scroll and no overlapping at 320, 375, 768, 1024, 1440 and 1920 px (checked in the browser pane);
  CLS stays 0 on the three page types (explicit sizes / aspect-ratio).
- **REQ-LAY-021** — CSS stays within the budget of SPEC-023 (raised to 200 KB uncompressed, one request); no CSS framework added;
  JS added for layout <= 1 KB (none expected).
- **REQ-LAY-022** — Contrast and focus visibility of SPEC-023 are kept; layout never depends on hover.
- **REQ-LAY-023** — Core JS hooks and ids listed in SPEC-006 and in the product/category tests keep working (tests stay green).

## Data requirements

None (templates and CSS only).

## Edge cases

- Browser without subgrid / container queries: auto-fill grid, cards stack their parts normally, no alignment across a row.
- Very long product names and one-product rows: `minmax(min(100%, …))`, line clamps keep rows even.
- RTL: logical properties; no RTL language is shipped.

## Acceptance criteria

- [ ] Home, category and product pages match the reference structure at 1440 and 375 px (reviewed in the browser pane).
- [ ] Cards in one listing row have aligned image, title, price and buttons (subgrid) without fixed heights.
- [ ] The same card renders correctly in listing, carousel, quick view and search (container queries).
- [ ] The stylesheet uses `@layer`, `grid`, `subgrid`, `@container`, `:has()`, `clamp()`; guarded by tests.
- [ ] `npm run verify` and `npm test` are green; no console errors; core hooks intact.
