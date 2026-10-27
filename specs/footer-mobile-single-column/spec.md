# SPEC-060 — Footer Link Groups: One Column on Mobile

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-047 (footer contacts and link groups)

## Problem

On phones the footer `details` groups (information, support, extras, account) sit in two columns because the grid used
`repeat(auto-fit, minmax(10.5rem, 1fr))`, which fits two tracks at ~360px. Labels get cramped.

## Purpose

Link groups stack in a single column on narrow screens.

## Scope

Theme `ocs` stylesheet, footer `.ocs-foot-groups`.

## Out of scope

Footer markup, the contacts block, desktop layout (from 62rem of footer width).

## Functional requirements

- **REQ-FMC-001** — Below 36rem of footer container width, `.ocs-foot-groups` is a single column.
- **REQ-FMC-002** — From 36rem the previous auto-fit multi-column layout applies; from 62rem the contacts/groups split is unchanged.

## Acceptance criteria

- [x] At 375px the four groups are stacked in one column.
- [x] Wider layouts unchanged.
