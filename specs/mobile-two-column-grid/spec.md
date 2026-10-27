# SPEC-061 — Mobile Product Grid: Always Two Columns

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-038 (phones: two card columns)

## Problem

On phones the listing grid uses `repeat(auto-fill, minmax(min(100%, 9rem), 1fr))`. At wider phone/page widths this yields
three columns, and the round cart button overlaps the price and image area of the narrow card.

## Purpose

Product cards on phones are always shown two per row, so the buy button never overlaps card content.

## Scope

Theme `ocs` stylesheet: `#ocs-grid`, `.ocs-viewed-grid`, `.ocs-cards` inside the phone container query (page width <= 40rem).

## Out of scope

Desktop and tablet column counts, list view, card markup, side-column (`#column-left/right`) single-column cards, carousels.

## Functional requirements

- **REQ-MGR-001** — When the page container is at most 40rem wide, listing grids have exactly two equal columns.
- **REQ-MGR-002** — Wider layouts keep the auto-fill behaviour; list view and side-column single-column cards are unchanged.

## Acceptance criteria

- [x] At 375px and 480px the category listing shows 2 cards per row; the cart button does not overlap the price.
- [x] Desktop/tablet unchanged.
