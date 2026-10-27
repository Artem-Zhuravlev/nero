# SPEC-071 — Sidebar Category Item Spacing

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-039 (category sidebar tree), SPEC-006 (theme)

## Problem

In the category sidebar tree the active parent and the active child rows touch each other, so their highlighted backgrounds merge into one shape.

## Purpose

Every row of a `.list-group` is visibly separated from its neighbours.

## Scope

`custom/theme/ocs/stylesheet/stylesheet.css`, section 40. Storefront only.

## Out of scope

Row padding, colours, markup, `core/`.

## Functional requirements

- **REQ-SSP-001** — Items of `.list-group` are laid out in a column with a `var(--sp-1)` gap, so highlighted (hover/active) rows never touch.

## Non-functional requirements

- **REQ-SSP-002** — `npm run verify` green; theme archive rebuilt (`npm run pack:theme`).

## Acceptance criteria

- [ ] Active parent and active child rows in the category sidebar are separated by a gap.
