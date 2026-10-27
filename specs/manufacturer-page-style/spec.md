# SPEC-075 — Brands Page Visual Redesign

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-006 (theme), SPEC-074 (brand search)

## Problem

The brands page (`product/manufacturer`) is a plain Bootstrap list: bare blue links, a text-only "alphabetical index" line, and large empty gaps between letter groups. It does not match the rest of the site (black accent, cards, rounded tiles).

## Purpose

Make the brands page look like the rest of the storefront: a letter navigation made of chips, and each letter group shown as a section with brand tiles in a responsive grid.

## Scope

`custom/theme/ocs/template/product/manufacturer_list.twig`, `custom/theme/ocs/stylesheet/stylesheet.css`, `custom/theme/ocs/javascript/theme.js` (only the selector updates, if markup changes). Storefront only, design tokens only (no colour literals).

## Out of scope

Brand logos on this page (the list model returns names only), brand detail page, `core/`.

## Functional requirements

- **REQ-MFP-001** — The letter index is a row of pill chips (`--r-pill`, `--bg-alt`; hover/focus in the accent colour) that scrolls to the letter section; it keeps working with the search of SPEC-074 (non-matching letters hidden).
- **REQ-MFP-002** — Each letter group is a section with a large letter heading and its brands as tiles in a CSS grid (4 columns desktop, 3 tablet, 2 phone); a tile is a `--bg-alt` rounded block with the brand name, a hover state (accent border/background shift) and a visible focus ring.
- **REQ-MFP-003** — Vertical rhythm is tight and even (no large gaps between groups); the search field is full-width on phones and aligned with the index.
- **REQ-MFP-004** — Anchor jumps are not hidden under the sticky header (`scroll-margin-top`).

## Non-functional requirements

- **REQ-MFP-005** — Colours and sizes come from the tokens in section 1 of the stylesheet; styles live in a cascade layer; no new dependencies; filtering from SPEC-074 still works. `npm run verify` green; theme archive rebuilt (`npm run pack:theme`).

## Acceptance criteria

- [ ] Desktop, tablet and phone widths show chips + tile grid with no horizontal scroll.
- [ ] Search, clearing and the "nothing found" message still work; letter chips follow the filter.
