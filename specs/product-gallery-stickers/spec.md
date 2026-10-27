# SPEC-073 — Product Gallery Stickers Top Right

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-029 (stickers), SPEC-006 (theme)

## Problem

On the product page the top-left stickers sit over the first thumbnail of the gallery (thumbnail column is on the left), hiding it.

## Purpose

Gallery stickers never cover the thumbnails.

## Scope

`custom/theme/ocs/template/product/product.twig` (gallery sticker slots). Storefront only.

## Out of scope

Product card stickers, bottom-left slot, sticker rules, `core/`.

## Functional requirements

- **REQ-PGS-001** — On the product page, stickers of positions `tl` and `tr` render in one top-right slot of the gallery (`tl` first, then `tr`).

## Non-functional requirements

- **REQ-PGS-002** — `npm run verify` green; theme archive rebuilt (`npm run pack:theme`).

## Acceptance criteria

- [ ] A discount sticker no longer overlaps the first gallery thumbnail; it is in the top-right corner of the gallery.
