# SPEC-037 — Swiper Carousels and Brief Gaps (Quick View, Option-level Stock)

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-23
**Refines:** SPEC-033, SPEC-035, SPEC-025, SPEC-029 · **Source:** `Список модулей.docx` (modules 1, 2, 3, 9, 14 and the general rules)

## Problem

1. Carousels use a hand-written `OCS.carousel` (scroll-snap). The client wants the library that ships with OpenCart (Swiper).
2. An audit of the brief against the code found gaps: the brief names **quick view** (modules 1, 3, 9) but the theme has none;
   pre-order and back-in-stock do not take **option availability** into account (modules 2, 14); back-in-stock notifies only when a
   product is saved in the admin; stickers are missing in module blocks and quick view (module 9).

## Purpose

Carousels on Swiper, a quick-view popup, and the missing behaviours of the brief's modules 2, 9 and 14.

## Scope

- Swiper 3.4.2 bundled with core (`catalog/view/javascript/jquery/swiper/`, already loaded by the header): hero slider, brand strip,
  product carousels (home, accessories, similar), product gallery on the product page.
- New extension `ocs_quick_view`.
- Changes in `ocs_preorder`, `ocs_back_in_stock`, `ocs_stickers`, `ocs_request`.

## Out of scope

- OCFilter and Simple (bought modules; filter chips and Simple compatibility are theirs to provide and cannot be tested without them).
- Inter font (needs a download permission), chat widget, Swiper upgrade (core's 3.4.2 is used as is; no download).
- Items of the moyo.ua reference that are not in the brief.

## Functional requirements

- **REQ-SWP-001** — `OCS.carousel` is replaced by Swiper instances created from the same markup contract
  (`.ocs-carousel` > track > items + prev/next + optional pagination). No second carousel implementation remains in `theme.js`.
- **REQ-SWP-002** — Hero: autoplay with pause on hover/focus, disabled by `prefers-reduced-motion`, pagination bullets, keyboard and
  swipe; first slide image keeps width/height, `fetchpriority="high"`, not lazy (REQ-HOM-001).
- **REQ-SWP-003** — Brand strip and product carousels: `slidesPerView: auto`, free swipe, prev/next buttons hidden when everything
  fits; buttons keep their `aria-label`; Swiper a11y messages come from the module language files.
- **REQ-SWP-004** — Product gallery: main image + thumbnails as linked Swiper instances, swipe on touch; the click still opens the
  zoom popup; first image has explicit size (REQ-PRD-030). Core hooks `.thumbnails`, `.thumbnail` are kept.
- **REQ-SWP-005** — Without JS or when Swiper is missing the lists remain a scrollable row (no empty page); Swiper CSS/JS are not
  loaded twice and do not break the core slideshow/carousel modules.
- **REQ-QVW-001** — Quick view: a button on every card (always visible on touch, on hover/focus on desktop) opens `OCS.modal` with
  image gallery, name, price/special, stickers, availability, options, quantity, "Add to cart" (AJAX cart, SPEC-026), "Quick order" /
  "Pre-order" / "Notify me" buttons (SPEC-025) and a link to the full page.
- **REQ-QVW-002** — The body is rendered by the server (`extension/module/ocs_quick_view/view&product_id=`), uses the same option
  markup and the core `checkout/cart/add` endpoint, so server validation is unchanged; disabled or unknown products return an empty body.
- **REQ-QVW-003** — Works from category, search, special, manufacturer, home carousels, viewed and similar blocks; one setting turns
  it off (REQ-MOY-001); the button is absent on cards when off.
- **REQ-QVW-004** — The body is never cached (prices and stock are per visitor); one product query set per open.
- **REQ-STK-010** — Stickers are shown in quick view and in module blocks (product carousel, viewed, accessories, similar) with the same
  slots and limits as category cards (refines REQ-STK-006).
- **REQ-OPT-001** — Pre-order and "Notify me" respect option availability: when the selected option value is out of stock
  (`subtract = 1` and `quantity <= 0`) the buy button turns into the pre-order / notify button for that selection; the server applies
  the same rule when the request is submitted; a request keeps the chosen options.
- **REQ-OPT-002** — Back-in-stock sends the notification when the product (or the subscribed option value) comes back in stock by any
  route: product edit, option edit, order cancel/stock return and import. Implementation: a secured cron endpoint
  (`extension/module/ocs_back_in_stock/cron&key=`) plus the existing admin-save event; each subscriber is mailed once.
- **REQ-OPT-003** — Admin list shows the option and the notification date; subscribers are not notified twice.

## Non-functional requirements

- **REQ-SWP-010** — JS added <= 6 KB net (the custom carousel code is removed); no new third-party download.
- **REQ-SWP-011** — Popups use `OCS.modal` only: closes on overlay click and Esc, focus is trapped and restored.
- **REQ-SWP-012** — uk/ru/en, PHP 7.4, each new feature switchable and off by default.
- **REQ-SWP-013** — Карусели с слайдами авто-ширины (бренды, блоки товаров на десктопе, категории, отзывы, мульти-hero) показывают только целые слайды: ширина слайдов подгоняется так, чтобы в ряд помещалось целое число, без обрезанного последнего.

## Data requirements

`oc_ocs_back_in_stock` already has the option key and the notification date columns (SPEC-025); new: settings
`module_ocs_quick_view_*`, `module_ocs_back_in_stock_cron_key`.

## Edge cases

- Product with required options: quick view shows them; the core endpoint returns the usual errors in place.
- Option value out of stock but product in stock: only that selection switches to pre-order / notify.
- Cron endpoint without or with a wrong key: empty answer, nothing is sent.
- Swiper script blocked: rows stay scrollable (REQ-SWP-005).

## Acceptance criteria

- [ ] No `OCS.carousel` code remains; home, product and gallery carousels work by swipe, buttons and keyboard.
- [ ] Quick view opens from cards, adds to cart, starts a quick order, closes on overlay click and Esc.
- [ ] Stickers appear in quick view and in module blocks.
- [ ] Pre-order / notify follows the selected option; cron sends each notification once.
- [ ] `npm run verify` is green; theme.js stays within the budget.
