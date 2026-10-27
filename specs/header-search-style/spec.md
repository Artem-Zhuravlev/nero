# SPEC-058 — Header Search Field Style

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-046 (form system), SPEC-006 (theme)

## Problem

The header search field and its button render as two detached bordered shapes: the pill radii set for `.ocs-head-search #search`
(layer `components`) lose to the generic `.form-control` of SPEC-046 (layer `overrides`).

## Purpose

One pill-shaped search control with the search button inside, consistent with the form system.

## Scope

- CSS only, `custom/theme/ocs/stylesheet/stylesheet.css`, layer `overrides`; header search `#search`.

## Out of scope

- Search dropdown (SPEC-028), mobile layout changes, templates, JS.

## Functional requirements

- **REQ-HSR-001** — The header search is a single pill container: shared radius, one border state, input without own border, round icon button inside on the right.
- **REQ-HSR-002** — Hover shows a border; focus-within shows accent border and focus ring on the whole container.

## Non-functional requirements

- **REQ-HSR-003** — Only theme tokens (`--bg-alt`, `--border`, `--accent`, `--r-pill`); no core edits.

## Edge cases

- Narrow screens (search spans full row) -> same pill, width 100%.

## Acceptance criteria

- [ ] Header search looks like one pill with the icon inside; focus ring wraps the whole control.
