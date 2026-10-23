# SPEC-034 — Category Listing: Sort, Load More, Filter Chrome, Card Details

**Status:** draft
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-23
**Refines:** SPEC-024 REQ-MOY-040..044 · **Depends on:** SPEC-023, SPEC-013, SPEC-029

## Problem

Core listing offers basic sort, numbered pagination and a minimal card.

## Purpose

A listing close to the reference: richer sorting, "load more", filter toolbar styling and a more informative card.

## Scope

Theme overrides of listing templates (category first, then search, special, manufacturer_info), a catalog event adding sort keys
and card data, JS for load more and view toggle. OCFilter is only styled.

## Out of scope

Filtering logic (OCFilter), stickers (SPEC-029), search suggestions (SPEC-028).

## Functional requirements

- **REQ-LST-001** — Sort options: price asc, price desc, newest, popular, recommended, with reviews; mapped to core sort keys
  where they exist, otherwise added by an event on `product/category` (SPEC-007). Selected sort is kept in the URL.
- **REQ-LST-002** — "Show N more" button loads the next page via AJAX and appends cards; numbered pagination remains in the
  HTML with real links and `rel=prev/next` data (SPEC-013); URL updates with `history.replaceState`.
- **REQ-LST-003** — Toolbar: product count, grid/list toggle, sort, per-page limit; button "Reset all filters" and chips of active
  filters when OCFilter parameters are present (read from URL, styling hooks only).
- **REQ-LST-010** — Card shows: product code (model), rating with review count, price with struck old price, discount badge,
  compare and wishlist icons, add-to-cart; optional variant chips when the product has option values flagged for display.
- **REQ-LST-011** — Subcategory chips with optional images above the grid (from the category tree, SPEC-027 library).
- **REQ-LST-012** — Grid 4/3/2/1 columns (SPEC-023 REQ-DSG-023); cards keep equal height; images lazy with width/height.
- **REQ-LST-013** — SEO text below the grid with collapse (shared with SPEC-033 REQ-HOM-040).

## Non-functional requirements

- **REQ-LST-020** — Load more adds one request per click and no duplicate cards; works without JS via normal pagination.
- **REQ-LST-021** — Extra data costs at most one extra query per listing (batched ratings/review counts); uk/ru/en; PHP 7.4.

## Data requirements

None; optional product flag for display options in an own table if variant chips are enabled.

## Edge cases

- Sort key without data ("with reviews" empty) -> falls back to recommended. Last page reached -> button hidden.
- Back button after load more -> returns to the page the visitor left (scroll restoration).

## Acceptance criteria

- [ ] Every sort option changes the order and survives reload; load more matches normal pagination content.
- [ ] Without JS the listing is fully navigable by pagination.
