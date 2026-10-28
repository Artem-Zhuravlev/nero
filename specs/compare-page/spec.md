# SPEC-057 — Compare Page: Categories, Differences Only, Customer-Friendly Table

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-044 (compare cookie), SPEC-042 (SVG icons), SPEC-024 (moyo.ua reference)

## Problem

`/index.php?route=product/compare` prints every compared product in one flat table (core layout, only restyled).
Phones, kettles and TVs end up side by side; attributes of unrelated goods make rows that are empty for most columns;
the customer has to find the differences by eye; the table is wide, scrolls badly on mobile and loses its row labels
and product headers when scrolling. The compare icon (two arrows, `i-compare`) does not say "compare".

## Purpose

A compare page like moyo.ua: products grouped by category, a switch "differences only", and a table that stays
readable (sticky product header and attribute column, remove per product, buy from the table). The icon becomes a balance scale.

## Scope

- Compare page `product/compare` (theme `ocs` template + event-driven data, no core edits).
- Grouping of the compared products by category with tabs; `?category=<id>` selects a group.
- "Differences only" switch (client-side, remembered per browser).
- Table usability: sticky header/first column, horizontal scroll on narrow screens, collapsible characteristic groups, remove and buy buttons.
- Replace icon `i-compare` (arrows) with a scale icon everywhere it is used (header, footer tab bar, card, product page, blog).
- Compare cookie limit rework (REQ-CMP-006).

## Out of scope

- Admin screens; new settings. Wishlist behaviour. Sharing/printing the comparison. Attribute reordering in admin.
- Comparison of blog articles (the existing article.twig button stays; only the icon changes through the sprite).

## Functional requirements

- **REQ-CMP-001** — Each compared product has one *compare category*: the deepest category (highest level in `category_path`;
  ties: lowest `category_id`) among its assigned categories; products without a category fall into the group "Other".
- **REQ-CMP-002** — The page shows tabs, one per compare category present in the cookie list, each with name and product count,
  ordered by the most recently added product. The active tab is `?category=<id>`; without it, the group of the most recently added product.
  Only products of the active group are in the table. An unknown/empty `category` falls back to the default group.
- **REQ-CMP-003** — A switch "Show only differences" above the table hides every characteristic row whose values are
  identical for all products in the table (empty values equal each other; a row where only some products have a value counts as different).
  Group header rows left without visible rows are hidden too. The state is stored in `localStorage` and applied without a reload;
  with JS disabled all rows are shown. A message "All characteristics are identical" appears when everything is hidden.
- **REQ-CMP-004** — The table: the first column (row labels) and the product header row (image, name, price, buy, remove) are
  `position: sticky`; the table scrolls horizontally inside its own wrapper on narrow screens; columns have equal width,
  min 180px; the page itself never gets a horizontal scrollbar at 320px.
- **REQ-CMP-005** — Each product column has: remove (✕, updates the cookie and the page), add-to-cart (`cart.add` with `minimum`,
  ajax cart behaviour of SPEC-026), price with old price, stock label, rating. Characteristic groups (attribute groups) are
  collapsible; a "General" group holds model, manufacturer, weight, dimensions, description. Name/image/price are in the header, not repeated as rows.
- **REQ-CMP-006** — The compare cookie holds up to 12 products in total, at most 4 per category (a fifth product in a full
  category pushes out the oldest one of that category). Trimming runs server-side on the next page load, there is no toast. The header counter shows the total.
- **REQ-CMP-007** — With fewer than 2 products in the active group the table shows the product plus a hint "add one more product
  of this category" with a link to the category listing; an empty list shows the existing empty state.
- **REQ-CMP-008** — The compare icon is a balance scale (`i-compare` in `ocs/sprite.twig`, SVG, `currentColor`, 24×24 viewBox,
  same stroke style as the other sprite icons); `fa-exchange` in `Ocs\Icons` keeps mapping to it, so no template needs editing.
- **REQ-CMP-014** — The table wrapper is capped at `9rem + columns × 22rem`, so a single compared product is not stretched across the whole content block.

## Non-functional requirements

- **REQ-CMP-010** — No extra queries beyond one batched query for product→category of the cookie ids and one for category names.
- **REQ-CMP-011** — Handlers never throw to the customer (REQ-EXT-062); if grouping fails, the page falls back to the flat core table.
- **REQ-CMP-012** — Works in uk-ua, ru-ru and en-gb; new strings go to the `ocs_bookmarks` language files.
- **REQ-CMP-013** — Accessibility: tabs are links with `aria-current`, the switch is a labelled checkbox, table headers have `scope`.

## Data requirements

No schema or settings changes. Cookie `ocs_cmp` format is unchanged (comma-separated ids); only limits change
(`Bookmarks::COMPARE_MAX` = 12 total, new `COMPARE_PER_CATEGORY` = 4). Trimming runs in the existing `hydrate` event, which rewrites the cookie.

## Interfaces

- Route `product/compare` (+ `&category=ID`). Events: `catalog/controller/product/compare/before` (narrows `session['compare']` to the active group),
  `catalog/view/product/compare/before` (tabs, groups, per-row `same` flags).
- Theme: `product/compare.twig`, CSS in `stylesheet.css`, JS in `theme.js` (switch, remove, collapse).
- Language keys: `text_cmp_only_diff`, `text_cmp_same`, `text_cmp_more`, `text_cmp_other`, `text_cmp_general`, `text_cmp_category`, `text_cmp_group`.

## Edge cases

- Product disabled/deleted but still in the cookie -> dropped silently; tab counts reflect existing products.
- Product in several categories -> only its compare category (REQ-CMP-001).
- All products identical -> message instead of an empty table.
- Customer prices hidden -> no price in the header, buy button as in the card.
- Cookie has > 12 ids or > 4 per category (old/hand-edited) -> trimmed on the next page load; the page renders the first 4 of the group.

## Acceptance criteria

- [ ] With 2 phones and 2 kettles in the list the page shows two tabs; each table holds only its group.
- [ ] "Differences only" hides identical rows and empty group headers, and persists after reload; off by default.
- [ ] At 360px the first column and header stay visible while scrolling the table; the page has no horizontal scrollbar.
- [ ] Removing a product updates the table, the tab counts and the header counter.
- [ ] The scale icon shows in the header, footer tab bar, product card and product page.
- [ ] `npm run verify` is green and `dist/ocs-theme-<VERSION>.ocmod.zip` is rebuilt.
