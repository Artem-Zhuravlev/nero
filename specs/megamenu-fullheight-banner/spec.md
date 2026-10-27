# SPEC-055 — Mega Menu: Full-Height Flyout and Banner Column

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-027 (mega menu), SPEC-006 (theme)

## Problem

On desktop the child panel (`.ocs-mega-fly`) of a hovered top-level item is positioned at `top: 0` of that
`<li>`, so it pops up next to the parent row and has its own height. The visitor sees a panel that jumps up and
down with the pointer. The per-category promo banner exists (`ocs_menu_category.banner_image/banner_url`,
edited in the mega menu module settings) but is a small 300x200 tile stacked under the links.

## Purpose

The flyout always covers the full height of the first-level list (like a fixed right-hand pane), and the
category's banner is shown as a dedicated column on its right edge.

## Scope

- Desktop (>= 768px) flyout geometry and layout in `stylesheet.css`; `ocs_mega_menu.twig` markup for the banner column.
- Banner image size in the `ocs_mega_menu` controller.
- Mobile (< 768px) accordion stays as is.

## Out of scope

- DB schema (banner fields already exist), admin form (stays: Extensions -> Modules -> Mega menu -> per-category
  "Promo tile image / link").
- Final visual design: the dark panel in the reference is only an example of layout, not of colours.

## Functional requirements

- **REQ-MMF-001** — On desktop the open flyout is anchored to the first-level list (`.ocs-mega-l1`), starts at its
  top edge and is exactly as tall as the list (`top: 0; bottom: 0` of the list), regardless of which parent item is hovered.
  If its content is taller it scrolls inside (`overflow-y: auto`).
- **REQ-MMF-002** — The flyout keeps the existing columns layout of groups/links; brands and "see all" stay inside it.
- **REQ-MMF-003** — If the category has a banner, it is a separate column at the right edge of the flyout, full
  flyout height, with the image covering the column (`object-fit: cover`); without a banner the links use the whole width.
- **REQ-MMF-004** — The banner links to `banner_url` (fallback: the category page) and has no effect on mobile
  other than appearing as a regular block below the links.
- **REQ-MMF-005** — Hover/click behaviour (open/close, `data-behaviour`) is unchanged; moving the pointer from the
  parent row into the flyout does not close it.

## Non-functional requirements

- **REQ-MMF-006** — No change to `core/`; theme colours come from existing CSS variables (no new hard-coded palette).
- **REQ-MMF-007** — Banner image is requested at 2x of its column width (e.g. 480x720), lazy-loaded, with explicit width/height.

## Interfaces

Markup: `.ocs-mega-fly` gets an inner `.ocs-mega-body` (cols + brands + see-all) and optional `.ocs-mega-promo`
as flex siblings. `.ocs-mega-l1` stays `position: absolute` and becomes the containing block (`.ocs-mega-item`
loses `position: relative` on desktop so the flyout positions against the list).

## Edge cases

- Very short first-level list (2-3 items) -> flyout min-height is the list height, so a minimum height (e.g. 360px) is set on the list.
- Very many children -> flyout scrolls internally.
- No banner -> body takes full width.

## Acceptance criteria

- [ ] Hovering any parent item shows the flyout with the same top/bottom as the left list.
- [ ] Banner (if set) fills the right column; empty banner leaves no gap.
- [ ] Mobile accordion unchanged.
- [ ] `npm run verify` green; theme archive rebuilt.
