# SPEC-029 — Product Stickers

**Status:** draft
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-23
**Refines:** SPEC-024 REQ-MOY-012 · **Brief:** module 9 · **Depends on:** SPEC-023 (`.ocs-slot-*`, `.ocs-badge`)

## Problem

Cards need labels ("Hit", "Top price", "-15%", "Pre-order") assigned manually or by rule.

## Purpose

A sticker engine: definitions, manual assignment, automatic rules, rendering into theme slots.

## Scope

Extension `ocs_stickers`: sticker CRUD, rules, per-product assignment, rendering on card, listings, search and quick view.

## Out of scope

Product data changes, discount calculation (uses core specials/discounts).

## Functional requirements

- **REQ-STK-001** — Built-in stickers: New, Hit, Sale, Top sales, Pre-order, Out of stock; plus custom text stickers per language.
- **REQ-STK-002** — Each sticker has text colour, background colour, position (top-left, top-right, bottom-left) and priority.
- **REQ-STK-003** — Manual assignment in the product form (tab added by event) and in bulk from the sticker list.
- **REQ-STK-004** — Automatic rules: added within N days, has special/discount (optionally showing `-N%` computed from old and new
  price), stock state, sales count above N, category/manufacturer.
- **REQ-STK-005** — Priority and limit: at most N stickers per position; higher priority wins.
- **REQ-STK-006** — Rendered in the theme slots on category, search, special, module blocks, product page and quick view.

## Non-functional requirements

- **REQ-STK-010** — Rules are evaluated in one batched query per listing page, cached (SPEC-019); page adds at most one query.
- **REQ-STK-011** — Colours are stored as hex picked in admin and emitted as `--badge-bg/--badge-fg` (SPEC-023 REQ-DSG-014); admin
  warns when contrast < 4.5:1. uk/ru/en; PHP 7.4.

## Data requirements

Tables `oc_ocs_sticker`, `oc_ocs_sticker_description`, `oc_ocs_sticker_product`; settings `module_ocs_stickers_*`.

## Edge cases

- Product matches two rules in one position -> priority decides. Sticker disabled -> removed everywhere on next cache refresh.

## Acceptance criteria

- [ ] Manual and rule-based stickers show in category, product and search; limit and priority respected.
- [ ] `-N%` badge equals the displayed prices.
