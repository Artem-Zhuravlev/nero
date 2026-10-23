# SPEC-030 — Recently Viewed Products

**Status:** in-progress
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-23
**Refines:** SPEC-024 REQ-MOY-014 · **Brief:** module 10 · **Depends on:** SPEC-023, SPEC-015

## Problem

Customers want to return to products they looked at.

## Purpose

Remember viewed products and show a "You recently viewed" block.

## Scope

Extension `ocs_viewed`: recording on product view, block on home and product page, storage for guests and logged-in customers.

## Out of scope

Personalised recommendations, analytics (SPEC-017).

## Functional requirements

- **REQ-VWD-001** — Each product page view records the product (most recent first, no duplicates, current product excluded from its own block).
- **REQ-VWD-002** — Block with N cards (setting) on home and product page; same card markup as listings.
- **REQ-VWD-003** — Guests: stored in a first-party cookie/localStorage list of ids; logged-in customers: also stored in
  `oc_ocs_viewed` and merged with the guest list at login.
- **REQ-VWD-004** — Disabled or deleted products are skipped; at most 20 ids are kept.

## Non-functional requirements

- **REQ-VWD-010** — Cookie is functional, not tracking (no third party); consent rules of SPEC-017 are documented. One DB query to load cards.
- **REQ-VWD-011** — uk/ru/en, PHP 7.4, switchable (REQ-MOY-001).

## Data requirements

Table `oc_ocs_viewed` (customer_id, product_id, viewed_at); settings `module_ocs_viewed_*`.

## Edge cases

- Cookies blocked -> block simply hidden. Customer logs out -> list falls back to the guest list.

## Acceptance criteria

- [ ] Viewed products appear newest first without duplicates; guest list merges at login.
