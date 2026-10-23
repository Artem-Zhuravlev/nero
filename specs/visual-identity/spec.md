# SPEC-039 — Visual Identity Pass: from "re-skinned OpenCart" to the moyo.ua feel

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-23
**Refines:** SPEC-023, SPEC-031, SPEC-033, SPEC-034, SPEC-035, SPEC-038 · **Reference:** https://www.moyo.ua (structure, density, components), apple.com (palette, calm surfaces)

## Problem

SPEC-038 modernised the layout technique (layers, grid, subgrid, container queries) but the result still reads as stock OpenCart with
small additions. Side-by-side with the reference the gaps are visual, not technical:

- the left column of category pages is the default bordered `list-group` plus default "Latest" modules;
- the header actions are plain icons, no labels, no counters; the catalogue is a button, not an always-open menu on the home page;
- the home hero is one wide image; the reference has a permanent category menu next to a multi-banner carousel, brand chips,
  big bold section titles with an accent word and tabbed product shelves;
- cards are tidy but generic: wishlist/compare sit in the bottom row, no product code, no overlay actions, no availability dot;
- tabs, breadcrumbs, pagination, forms, tables and alerts keep the Bootstrap 3 look;
- one system font at normal weights, icons are Font Awesome 4.

## Purpose

A coherent visual language (type, components, page compositions) that makes the three key pages look like a modern electronics store:
dense, confident, calm — moyo's structure with apple.com's palette.

## Scope

Theme stylesheet, theme overrides (`common/header`, `common/footer`, `product/*`, `ocs/card`, `ocs/options`), home block templates
(slider, brand strip, product carousel, category wall), small JS in `theme.js` (counters, tabs, sticky summary).

## Out of scope

New business features, admin pages, OCFilter/Simple markup (their slots are styled), real photography (the demo catalogue keeps
white placeholder images), an icon-font replacement beyond the listed set.

## Functional requirements

- **REQ-VIS-001** — Typography: one self-hosted variable font (Inter, SIL OFL, latin + cyrillic subset, `font-display: swap`,
  preloaded) with a system fallback; scale with weights 400/500/700/800, tight tracking on headings (`letter-spacing: -0.02em`),
  tabular numerals for prices. **Needs permission to download the font files** (about 100 KB woff2).
- **REQ-VIS-002** — Header like the reference: slim top row (logo, city selector, "Contact us" dropdown with phones and messengers,
  text links), main row (accent "Catalogue" button with grid icon, wide pill search with focus ring and styled suggestions, action
  buttons with the icon above a small label: Account, Compare, Wishlist, Cart) with count badges kept in sync with the core counters.
- **REQ-VIS-003** — Home page composition: on wide containers a permanent category menu (the mega-menu panel rendered in place,
  flyouts on hover) next to a multi-banner carousel (three tall banners visible on wide screens, one on phones); brand chips row
  with a "More" tile; section titles in a heavy weight with an accent-coloured word (`[[word]]` markup in module titles);
  "Best in categories" shelf with category chips that switch product lists; benefits strip; newsletter band.
- **REQ-VIS-004** — Product card: sticker chips top-left, product code top-right, wishlist and compare as round overlay buttons
  revealed on hover/focus (always visible on touch), large image, rating with review count, availability dot and text, price block
  with the old price and discount, a round accent "add to cart" button beside the price; quick order stays a link under it;
  hover lift and smooth image fade-in; compact variant inside narrow containers.
- **REQ-VIS-005** — Category page: breadcrumbs as a quiet path; page title with product count; the sidebar is a tree
  (current branch open, counts muted) plus a filter container with collapsible groups (OCFilter-ready) instead of the default
  list-group and bestseller modules; sort as chips/segmented control; active filters as removable chips; pagination restyled with
  "Show more" as the primary control.
- **REQ-VIS-006** — Product page: gallery with vertical thumbnails on wide screens (horizontal on phones); price block with
  instalment line when SPEC-036 is on; buy row with a large cart button plus round wishlist/compare buttons; delivery, pickup and
  payment as an icon list; a compact sticky summary bar (image, name, price, buy) appears after the buy box scrolls out; zebra
  specification table; review summary with a star distribution.
- **REQ-VIS-007** — Base components re-skinned so no Bootstrap 3 default look remains: buttons (primary, secondary, ghost, icon),
  form controls and floating labels, selects, checkboxes/radios, dropdowns, tabs, breadcrumbs, pagination, panels, list-groups,
  tables, alerts, tooltips, modals.
- **REQ-VIS-008** — Icons: an inline SVG sprite (about 40 icons: search, cart, heart, compare, user, grid, chevrons, truck, shield,
  return, headset, eye, phone, map-pin, mail, close, check, star, ...) used by the new header, card, benefits and product blocks;
  Font Awesome stays for core and third-party markup.
- **REQ-VIS-009** — Motion and states: 150–250 ms transitions on interactive elements, image fade-in, skeleton blocks for
  AJAX bodies (quick view, cart panel, suggestions), designed empty states (empty cart, no results), focus rings; everything
  respects `prefers-reduced-motion`.
- **REQ-VIS-010** — Demo content for review: a documented seed (SQL/PHP script) that fills the local stand with a realistic
  catalogue — categories with icons, brands with logos, products with badges and prices — so the pages can be judged with data.

## Non-functional requirements

- **REQ-VIS-020** — Contrast >= 4.5 for text in both colour schemes (the existing test); colour literals only in the token section.
- **REQ-VIS-021** — CSS <= 200 KB, JS added <= 6 KB, the font preloaded once; LCP image stays high priority; CLS 0.
- **REQ-VIS-022** — Core JS hooks keep working (tests stay green); uk/ru/en strings for every new label; PHP 7.4.

## Edge cases

- No JS: counters stay hidden, chips become links, sections remain readable.
- Missing font: the fallback stack keeps the same metrics closely (`size-adjust`).
- Long names, many categories, a store without brands or banners: blocks collapse without empty frames.

## Acceptance criteria

- [ ] Home, category and product pages reviewed side by side with the reference at 1440 and 375 px; no default-OpenCart components visible.
- [ ] Header actions with badges, home sidebar + multi-banner carousel, tabbed shelf, overlay-action cards, sticky product summary work.
- [ ] `npm run verify` and `npm test` are green; keyboard and reduced-motion checks pass.

## Delivery notes (deviations from the requirements above)

- REQ-VIS-006: thumbnails stay in a horizontal strip below the main image (vertical thumbnails are not done); the review summary with the star
  distribution is not done; the sticky summary, buy row, zebra table and delivery/credit blocks are.
- REQ-VIS-005: the filter container for OCFilter is not added as markup (the sidebar tree and sort chips are); active-filter chips remain
  OCFilter's own job.
- REQ-VIS-007: floating labels are not done; every other listed component is restyled.
- REQ-VIS-008: the sprite covers the new header, card, home menu and benefits; Font Awesome stays for core and third-party markup.
- REQ-VIS-003: the "More" brand tile is not added; the brand strip keeps its arrows.
