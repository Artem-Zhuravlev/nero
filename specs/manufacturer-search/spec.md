# SPEC-074 — Brand Search on Manufacturer List

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-006 (theme)

## Problem

The brands page (`product/manufacturer`) lists every brand grouped by letter; finding one in a long list means scrolling.

## Purpose

A search field on the brands page filters the list as the visitor types.

## Scope

`custom/theme/ocs/template/product/manufacturer_list.twig`, `custom/theme/ocs/javascript/theme.js`, `custom/theme/ocs/stylesheet/stylesheet.css`, language strings for the placeholder/empty message. Storefront only.

## Out of scope

Server-side search, brand pages, `core/`.

## Functional requirements

- **REQ-MFS-001** — The brands page shows a text input above the letter index when brands exist.
- **REQ-MFS-002** — Typing filters brand links client-side (case-insensitive substring); letter headings and index links of groups with no matches are hidden; a "nothing found" message shows when no brand matches.
- **REQ-MFS-003** — Clearing the input restores the full list.

## Non-functional requirements

- **REQ-MFS-004** — Works without a page reload, no new dependencies; template hand-written (generator mark removed). `npm run verify` green; theme archive rebuilt (`npm run pack:theme`).

## Acceptance criteria

- [ ] Typing part of a brand name leaves only matching brands and their letters; clearing restores all.
