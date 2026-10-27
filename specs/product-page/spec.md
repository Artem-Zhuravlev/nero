# SPEC-035 — Product Page: Tabs, Key Attributes, Questions, Accessories, Similar

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-23
**Refines:** SPEC-024 REQ-MOY-051..053, 055 · **Depends on:** SPEC-023, SPEC-032 (delivery block), SPEC-029

## Problem

The core product page is a basic gallery + tabs; the reference has sticky section navigation, key attributes, Q&A and
accessory/similar carousels.

## Purpose

A richer product page built on the SPEC-023 override.

## Scope

Product override extension, `ocs_product_extras` module (Q&A, accessories), carousels, sticky section bar, key attributes.

## Out of scope

Delivery block (SPEC-032), credit block (SPEC-036), reviews engine (core reviews are kept), 3D/video viewers.

## Functional requirements

- **REQ-PRD-001** — Sticky section bar with anchors: About, Specifications, Reviews/Questions, Description, Availability in stores
  (if SPEC-032 data), Compare, Accessories; highlights the section in view; collapses to a scrollable row on mobile.
- **REQ-PRD-002** — Key attributes: up to 4 attributes (chosen per category or globally) shown under the title; manufacturer link in
  breadcrumbs and next to the title.
- **REQ-PRD-003** — Product code, availability state (SPEC-026), rating and review count linking to the reviews section.
- **REQ-PRD-010** — Questions and answers: visitor asks (name, e-mail, text), moderation in admin, answers by staff or customers;
  shown with reviews; rate-limited and spam-protected.
- **REQ-PRD-020** — Accessories carousel from linked products of kind "accessory" (own link table, admin tab in product form).
- **REQ-PRD-021** — Similar products carousel: same category and brand, price band, excluding current; cached.
- **REQ-PRD-030** — Gallery: main image with thumbnails, zoom/popup through `OCS.modal`, swipe on touch, first image has explicit
  dimensions (LCP).
- **REQ-PRD-031** — Buy box keeps `#button-cart`, quantity, wishlist/compare; has slots for quick order (SPEC-025) and the
  delivery/credit blocks.

## Non-functional requirements

- **REQ-PRD-040** — Schema.org Product and BreadcrumbList (REQ-THM-043; superseded by SPEC-080, which prints them from one event); JS added <= 10 KB; core product JS hooks preserved (REQ-THM-013).
- **REQ-PRD-041** — uk/ru/en, PHP 7.4; each extra (Q&A, accessories, similar) switchable.

## Data requirements

Tables `oc_ocs_question`, `oc_ocs_product_accessory`, `oc_ocs_attribute_key`; settings `module_ocs_product_extras_*`.

## Edge cases

- Product without attributes -> key block hidden. No accessories -> tab removed.
- Question flood -> rate limit, moderation queue.

## Acceptance criteria

- [ ] Section bar scrolls to and highlights each section; tabs of core JS still work.
- [ ] Q&A moderation and accessory/similar carousels work and can be switched off.
