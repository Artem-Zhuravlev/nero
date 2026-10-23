# SPEC-027 — Mega Menu, Category Wall, Side Category Menu

**Status:** in-progress
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-23
**Refines:** SPEC-024 REQ-MOY-010 · **Brief:** modules 5, 6, 8 · **Depends on:** SPEC-023, SPEC-012

## Problem

Core menu is a two-level dropdown; large catalogues need a vertical mega menu, a category tile page and a sidebar tree.

## Purpose

Three navigation modules fed from OpenCart categories with extra display settings.

## Scope

Extensions `ocs_mega_menu`, `ocs_category_wall`, `ocs_category_menu`; admin settings per category (icon, popular flag, banner), mobile variant.

## Out of scope

Filters (OCFilter), SEO URL rules (SPEC-013), category data model (SPEC-012).

## Functional requirements

### Mega menu

- **REQ-MNU-001** — Vertical list of top categories with an icon per category (image or icon class) and highlight flag
  (popular/promo); hover (desktop), click, keyboard and touch all open the fly-out.
- **REQ-MNU-002** — Fly-out panel with multi-level subcategories in columns, optional image per category, optional brand
  group (manufacturer links) and promo tile (image + link) at the end of a section.
- **REQ-MNU-003** — Settings: number of levels (1-3), columns, behaviour (hover/click), open on home page by default.
- **REQ-MNU-004** — Mobile: full-screen drill-down panel instead of fly-out (accordion levels, back button).

### Category wall

- **REQ-MNU-010** — Grid of categories with image, name, link; optional subcategory list; columns per breakpoint
  (desktop/tablet/mobile) in settings; selection, sort order and image size configurable.
- **REQ-MNU-011** — Usable as a standalone "All categories" page and as a home-page block; data comes from categories automatically.

### Side category menu

- **REQ-MNU-020** — Compact tree for sidebar/category page, expands nested items, highlights current category and its
  parents, remembers open branch within the session.
- **REQ-MNU-021** — Placed through layouts (column left/right, category page).

## Non-functional requirements

- **REQ-MNU-030** — One cached category tree query per language (SPEC-019); markup is accessible (`nav`, `aria-expanded`).
- **REQ-MNU-031** — uk/ru/en, PHP 7.4, switchable (REQ-MOY-001); only token colours (REQ-DSG-001).

## Data requirements

Table `oc_ocs_menu_category` (category_id, icon, popular, banner_image, banner_url, sort); settings per module.

## Edge cases

- Category without image -> placeholder icon. Disabled category -> hidden in all three.
- 500+ categories -> level limit and cache keep the page fast.

## Acceptance criteria

- [ ] Menu opens by hover, click, keyboard and touch; mobile drill-down works.
- [ ] Wall and side menu reflect category changes within cache TTL.
