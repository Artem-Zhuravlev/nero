# SPEC-048 — Product Gallery Lightbox, YouTube Video, Store-wide Info Blocks

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-26
**Refines:** SPEC-035 (product page extras), SPEC-037 (swiper gallery), SPEC-047 (side card, theme contacts settings), SPEC-006 (theme settings), SPEC-044 (cart states)

## Problem

Review items 21-23:

21. Clicking the product photo opens the default Magnific popup (black overlay, tiny image, caption "MacBook 1 of 5", default arrows). It must look like the reference:
    a white modal with the large image, light arrows on both sides, a thumbnail strip under the image, the close button in the corner and, bottom right,
    the product price with a buy button.
22. A product can have a YouTube video. It must be added in the admin product form, appear as the **last item of the gallery** (page gallery and lightbox) and a **video icon** must show that the
    product has a video (on the product page and in product cards).
23. A block of custom data (delivery, returns, payment, ...) must be set **once** in the theme settings and shown identically on **every** product.

## Scope

Theme `ocs` (`product.twig` gallery, a lightbox module in `theme.js`, stylesheet, card badge), `ocs_product_extras` (video table, admin product-form field through the existing OCMOD and
admin events, catalog data for the gallery and the listing flag), theme settings form (info blocks editor, per-language), tests and browser checks.

## Out of scope

Other video hosts (Vimeo, uploads), autoplay with sound, video in the AJAX search, per-product info blocks (blocks are the same for all products by requirement), 360° views.

## Functional requirements

### Lightbox (item 21)

- **REQ-LBX-001** — The gallery images open in the theme's own lightbox (`OCS.lightbox`, built on `OCS.modal`) instead of Magnific for the product page: white dialog, large image
  (the `popup` image size, `object-fit: contain`), previous/next arrows on both sides, a counter, a close button, thumbnail strip under the image (current one highlighted, strip scrolls
  to it), thumbnails and arrows reachable by keyboard; Esc closes, Left/Right switch, swipe on touch, focus is trapped and restored (as `OCS.modal`), `aria-label`s from language strings.
- **REQ-LBX-002** — Bottom right of the dialog the product summary is shown: old and current price and a buy button that forwards to the page button (same mechanism as the
  side card: "in cart" state of SPEC-044, pre-order / out-of-stock variants). Below 768 px the summary is a compact bar under the thumbnails.
- **REQ-LBX-003** — Images load lazily (only the opened and the neighbouring ones are fetched at the full size); no layout jump when switching; the lightbox opens from the main gallery
  image, the thumbnails strip of the page and from the keyboard (Enter). Without JS the image link still opens the large image in the browser.
- **REQ-LBX-004** — The Magnific popup of core is no longer initialised on the product page; its CSS/JS stay loaded for other pages (no core edit).

### YouTube video (item 22)

- **REQ-VID-001** — The admin product form gets a "Video" field (YouTube URL or id) in the existing OCS extras tab; accepted forms: `youtu.be/ID`, `youtube.com/watch?v=ID`, `/embed/ID`, `/shorts/ID`,
  a bare 11-character id; anything else is rejected with a message. Stored in `oc_ocs_product_video (product_id, video_id)` (id only, 11 characters `[A-Za-z0-9_-]`), deleted with the product,
  covered by a migration and by the product add/edit/delete events.
- **REQ-VID-002** — On the product page the video is the last gallery slide and the last thumbnail (poster from `https://i.ytimg.com/vi/ID/hqdefault.jpg`, play icon over it). The player is not loaded
  until the visitor clicks (privacy: `youtube-nocookie.com/embed/ID?autoplay=1&rel=0`, a sandboxed `iframe`, `loading="lazy"`, `allowfullscreen`); the same slide is the last item of the lightbox.
  Leaving the slide stops the video.
- **REQ-VID-003** — A product with a video shows a **video icon** (SVG play badge): on the product page next to the title or on the gallery thumbnail, and in every product card
  (listing, modules, carousels) as a small badge on the image; one query for the whole list, not one per card. Badge has an accessible name ("Video").
- **REQ-VID-004** — The video id is validated again on output; the iframe source is built only from the id; Content-Security: no inline event handlers added; the poster is an `img` with `alt=""`.

### Store-wide info blocks (item 23)

- **REQ-INF-001** — The theme settings form gets an "Info blocks" editor (same row editor pattern as SPEC-047): per row an icon (fixed SVG set: truck, return, card, shield, gift, clock, phone,
  info), a title and a text per language, an optional link (URL, scheme-checked) and a sort order; up to 10 rows. Stored in `theme_ocs_info_blocks`; values are sanitised on save
  (`Ocs\InfoBlocks`) and escaped on output (plain text, line breaks kept, no HTML).
- **REQ-INF-002** — The blocks are rendered on every product page in the info column under the buy form as compact cards (icon, title, text, "more" link when set); the same blocks for every
  product, the current language with fallback to the store's default language; nothing is rendered when no block is filled in. No lists and no headings above `h3` (SPEC-045 rules: titles are `<p>`/`<h3>`).
- **REQ-INF-003** — Existing delivery (city) and instalment blocks of SPEC-032/036 stay as they are; the info blocks follow them.

## Non-functional requirements

- **REQ-LBX-020** — Lightbox and video slide work from 320 px; no horizontal scroll; focus visible; reduced motion respected; `theme.js` and the stylesheet stay within their budgets
  (budgets may be raised with a note in this spec if the lightbox needs it).
- **REQ-VID-020** — No request to YouTube before a click (checked in the browser network log); no cookies set by the page load.
- **REQ-INF-020** — No extra queries per product beyond reading settings; settings are already loaded by the core.

## Edge cases

- Product without additional images -> the lightbox shows one image, arrows hidden; with video only -> video is the only slide.
- Invalid or removed video id -> the slide is skipped, no broken iframe, the admin field shows its error.
- Video poster fails to load -> neutral placeholder box with the play icon.
- Info block without a title or without text -> the row is dropped on save.

## Open decisions (default in bold)

1. Video storage: **own table `oc_ocs_product_video` via the existing admin extras tab**; or a free-text product attribute.
2. One video per product: **yes** (the review asks for "a video"); or a list.
3. Where the info blocks sit: **in the info column under the buy form**; or a separate "Delivery and payment" section among the product sections (with its own `h2`).
4. Info block layout: **compact cards in a column**; or an accordion (`<details>`).
5. Lightbox for other pages (category zoom): **product page only**.

## Implementation notes

- Defaults of the open decisions were used (approved 2026-10-26). `theme.js` budget raised from 68 to 80 KB (lightbox and video slide); the stylesheet stays far below its 200 KB budget.
- The video is stored in `oc_ocs_product_video` (migration `ocs_product_extras/002_video`); the admin field sits in the existing OCS extras tab. A value that is not a YouTube link is not stored and the next
  load of the form shows the error (the product itself is saved by the core model, an after-event cannot reject it).
- Cards are flagged by one `catalog/view/*/before` event (`ocs_product_extras_cards`, sort 60): it collects product ids from the view data and runs one `IN (...)` query; modules and carousels need no code of their own.
- Info blocks use the same language fallback as the contacts. `config_language` is the visitor's language at run time, so the store default is read from the settings table; this also fixes the fallback of the SPEC-047 contacts (previously both arguments were the visitor's language).
- Colour tokens `--scrim` / `--on-scrim` were added for the video overlay (the colour-literal test only allows literals in the token section).
- The product-only video (no images) falls back to the core thumbnail markup and shows no video slide; a product always has a main image in practice.
- Verified on the test store: lightbox opens from the main photo (white dialog, arrows, thumbnails, price, buy button with "in cart" state), ArrowLeft wraps to the video, no request to YouTube before the click (only the poster from i.ytimg.com),
  the iframe is nocookie and sandboxed, video thumbnail and slide on the page, video badge on the two flagged cards, three info blocks under the buy form. The admin product form and the theme form editor were rendered/tested through PHP and Twig, not used in a logged-in admin.

## Acceptance criteria

- [x] Click on the product photo opens the white lightbox with arrows, thumbnails, price and buy button; keyboard, swipe and Esc work; the buy button adds the product ("in cart" state).
- [x] A product with a YouTube URL saved in the admin shows the video as the last slide/thumbnail in the page gallery and the lightbox; no request to YouTube before the click; a video badge shows on the product page and on its cards.
- [x] Info blocks entered once in the theme settings show on every product page in the current language; empty settings render nothing.
- [x] `npm test`, `npm run verify` green; package rebuilt; screenshots at 360/768/1280 px reviewed.
