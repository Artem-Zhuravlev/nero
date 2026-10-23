# SPEC-033 — Home Page Blocks

**Status:** draft
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-23
**Refines:** SPEC-024 REQ-MOY-030..034 · **Depends on:** SPEC-023, SPEC-016, SPEC-027

## Problem

Core home page offers a slideshow and generic product modules; the reference has category-based carousels, a brand strip,
newsletter and SEO text.

## Purpose

A set of home-page modules placed through layouts.

## Scope

Extensions `ocs_home_slider`, `ocs_brand_strip`, `ocs_product_carousel`, `ocs_newsletter`; SEO text via category/home description.

## Out of scope

Category wall (SPEC-027), recently viewed (SPEC-030), blog blocks (SPEC-016).

## Functional requirements

- **REQ-HOM-001** — Hero slider: slides (image desktop/mobile, link, title, text) per language, schedule, autoplay with pause on
  hover/focus, arrows and dots; first slide image has width/height and is the LCP image (REQ-THM-042); reduced-motion disables autoplay.
- **REQ-HOM-010** — Brand strip: manufacturer logos with links, selection and sort in settings, horizontal scroll on mobile.
- **REQ-HOM-020** — Product carousel block: source = category + mode (bestsellers, newest, discounted, manual list), N cards,
  title per language, "show all" link, prev/next; several instances allowed.
- **REQ-HOM-030** — Newsletter block: e-mail field + consent text; saves via core newsletter flag for customers or an own list
  (SPEC-018 hook for providers); double opt-in optional; spam protection.
- **REQ-HOM-040** — SEO text block with "read more" collapse (CSS + `details` fallback), text from home/category description.

## Non-functional requirements

- **REQ-HOM-050** — With all blocks on, home meets REQ-MOY-080 budgets; images lazy except the first slide; one cached query per block.
- **REQ-HOM-051** — uk/ru/en, PHP 7.4, switchable; carousels keyboard-accessible with labelled controls.

## Data requirements

Tables `oc_ocs_slide`, `oc_ocs_slide_description`, `oc_ocs_subscriber`; settings per module instance (core `module` table).

## Edge cases

- No slides scheduled -> block hidden. Category without products -> carousel hidden.
- Subscriber already exists -> same success message (no enumeration).

## Acceptance criteria

- [ ] Each block can be placed, ordered and switched off via layouts.
- [ ] Home LCP image has dimensions and is not lazy.
