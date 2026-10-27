# SPEC-062 — Mobile Card Tools Always Visible

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-006 (theme)

## Problem

Wishlist and compare buttons on product cards appear only on hover. Phones have no hover, and `@media (hover: none)` is not matched in narrow desktop/emulated views.

## Functional requirements

- **REQ-MCT-001** — When the page container is at most 40rem wide, `.ocs-tool` buttons are always visible.
- **REQ-MCT-002** — Wider layouts keep the hover reveal.

## Acceptance criteria

- [x] At 375px the heart and compare icons show on every card without hover.
