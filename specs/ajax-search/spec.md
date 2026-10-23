# SPEC-028 — AJAX Search with History and Popular Queries

**Status:** in-progress
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-23
**Refines:** SPEC-024 REQ-MOY-011 · **Brief:** module 7 · **Depends on:** SPEC-013, SPEC-023

## Problem

Core search needs a page reload and does not search by model/SKU suggestions or remember queries.

## Purpose

Instant search dropdown with product suggestions, recent and popular queries.

## Scope

Extension `ocs_ajax_search`: JSON endpoint, dropdown UI under the header search field, admin settings and popular-query list.

## Out of scope

Full-text engine (Elasticsearch etc.), voice input (P3), search results page layout (SPEC-034).

## Functional requirements

- **REQ-SRC-001** — Suggestions appear while typing (debounce 250 ms) from a configurable minimum length; no page reload.
- **REQ-SRC-002** — Each suggestion shows image, name, price, old price/discount and link; search by name, model, SKU.
- **REQ-SRC-003** — Settings: result count, minimum length, include categories/brands in suggestions.
- **REQ-SRC-004** — "Show all results" link to the full search page with the same query.
- **REQ-SRC-005** — Empty result and server error states with a message and suggested popular queries.
- **REQ-SRC-006** — Keyboard (arrows, Enter, Esc) and touch support; mobile full-width layout.
- **REQ-SRC-010** — On focus with empty field: recent queries of the visitor (localStorage, max 10, "clear history") and popular
  queries / categories / products configured in admin.
- **REQ-SRC-011** — Server limits: query length 100, 10 requests/10 s per IP, results escaped (SPEC-001 REQ-ARCH-063).

## Non-functional requirements

- **REQ-SRC-020** — Endpoint answers < 200 ms on 50k products (indexed columns, LIKE prefix + model exact, cache of popular queries).
- **REQ-SRC-021** — uk/ru/en, PHP 7.4, switchable (REQ-MOY-001).

## Data requirements

Table `oc_ocs_search_popular` (query, language_id, sort, status); settings `module_ocs_ajax_search_*`; optional index
migration on `oc_product(model, sku)`.

## Edge cases

- Layout keyboard mismatch (Cyrillic typed on Latin layout) -> optional transliteration suggestion (P3).
- Disabled product never appears.

## Acceptance criteria

- [ ] Typing shows products within the budget; arrows + Enter open the product.
- [ ] Recent queries persist across pages and can be cleared.
