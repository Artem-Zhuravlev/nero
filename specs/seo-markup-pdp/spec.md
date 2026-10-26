# SPEC-045 — SEO Heading Markup and Product Page Info Block

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-26
**Refines:** SPEC-006 (theme), SPEC-013 (SEO), SPEC-035 (product page), SPEC-023 (visual design)

## Problem

Review item 16 and the product page remark:

1. Templates break SEO heading rules: the price on the product page is an `<h2>` (`product.twig`, two places), `<h2>` is used for non-section text
   (review form title, HTML module title), and the heading levels skip (`h1` -> `h3` module titles -> `h4` card titles, no `h2`).
2. Product titles must be `<h4>` in modules and in the category listing. Audit: `card.twig` (category, search, special, manufacturer,
   carousel, viewed), `featured`, `latest`, `bestseller`, `special`, `featured_product` already use `<h4>`; the product page "related" block uses `<h4>`.
   The review screenshot shows the live site on an older package, so every producer of a product title is checked by a test, not assumed.
3. The product page info column (right of the gallery) is the default OpenCart block: stacked `<ul class="list-unstyled">` lists for
   manufacturer/model/stock/price/points, and a `btn-group` of two buttons above the `<h1>`. It must be re-laid-out as a designed block.

## Scope

Theme `ocs` templates: `product/product.twig` (info column), `ocs/card.twig`, `extension/module/*.twig`, `product/category.twig`, review and
tab headings, the `html` module; extension templates that print headings (`ocs_product_carousel`, `ocs_viewed`, `ocs_newsletter`, ...); stylesheet;
a test that checks heading rules on the templates.

## Out of scope

Admin templates, e-mail templates, payment vendor templates, the URL/meta-tag side of SEO (SPEC-013).

## Functional requirements

### Heading rules

- **REQ-SEO-001** — Exactly one `<h1>` per page, the page subject. Prices, labels, stock, counters and button captions are never in `<h1>`-`<h6>`;
  they use `<p>`/`<div>`/`<span>` with a class (price on the product page: `<p class="ocs-price">`, schema.org `Offer` markup kept).
- **REQ-SEO-002** — Product titles are `<h4>` (link inside) in every product list: category, search, special, manufacturer, modules
  (featured, latest, bestseller, special, carousel, viewed, related). One shared markup, no `<h3>`/`<h2>` product titles.
- **REQ-SEO-003** — Section headings follow the outline without skipping a level inside the main content: section and module titles and the
  tab headings are `<h2>` (see open decision 1); sub-blocks (credit, ask-a-question form, cart dialog titles) are one level below their section.
- **REQ-SEO-004** — Heading text is never empty; decorative text that only styles is not a heading. A test scans all theme and extension templates:
  no heading holds only a price/count variable, headings do not nest, no level is skipped in the product, category and home templates.

### Product page info block

- **REQ-PDP-001** — The info column is rebuilt without `<ul>`/`<li>` lists: title (`h1`), a row of key attributes as chips (`<div>`/`<span>`),
  a definition block (`<dl>`) for brand / article number / availability (article and availability follow the SPEC-044 switches), a price block
  (old price, current price, discount badge, tax/points as secondary text), then the buy form. The wishlist/compare buttons move into the title row
  as icon buttons (SPEC-044 states kept: `aria-pressed`).
- **REQ-PDP-002** — Hooks used by core JS and the theme are unchanged: `#product`, `#button-cart`, `#input-quantity`, `#ocs-buy-slot`, option
  inputs, the sticky buy bar, quick-order / pre-order buttons.
- **REQ-PDP-003** — Visual check at 360, 768 and 1280 px; spacing uses `--sp-*` tokens, no new raw px spacing (SPEC-043 check).

## Non-functional requirements

- **REQ-SEO-020** — No change to controllers, data or form field names; the structured data (JSON-LD of SPEC-035) is unchanged.
- **REQ-SEO-021** — Stylesheet and `theme.js` stay within their budgets.

## Edge cases

- A product without a price (customer-only prices) -> no price block, no empty heading.
- A product without manufacturer/reward/points -> the rows are omitted, `<dl>` stays valid.
- Third-party module titles that use `h2`/`h3` -> out of our control; the theme's own module overrides are fixed.

## Open decisions (default in bold)

1. Section/module title level: **`<h2>` for section and module titles, `<h3>` for sub-blocks, `<h4>` for product titles** (outline h1 > h2 > h3,
   product cards are leaf items); or keep `<h3>` module titles as they are today (outline jumps h1 -> h3).
2. Product titles in the "recently viewed" and related carousels also `<h4>`: **yes**.
3. Key attributes as chips vs a two-column table: **chips**.

## Implementation notes

- Defaults of the open decisions were used (approved 2026-10-26). Module, section and widget titles are `<h2>` (modules keep their look through `.ocs-sec-title`);
  product titles stay `<h4>` leaf items, so the skipped-level test ignores headings that print `product.name`. Dialog titles (cart panel, city select, request forms) stay `<h3>`.
- The "write a review" and HTML-module titles are section headings and stay `<h2>`; only the price headings were wrong.
- Added: a visually hidden `<h1>` on the home page (title from `config_meta_title`, set in the `ocs_theme_display` event); footer group titles are `<h2 class="ocs-fh">`.
- The discount badge from REQ-PDP-001 was not added: the product page has no discount value in its data; old price and current price are shown.
- Not changed: the stores and order-tracking pages of `ocs_city` (their `h3`/`h4` sit under the page `h1`), account/checkout generated templates (core outline).
- Eight module templates and `blog/article.twig` are hand-written now (left the generator's regenerate list); generated templates dropped from 78 to 68.
- Checked on the test store at 375 and desktop width: no horizontal scroll, one h1 on home/category/product/search, no price in any heading.

## Acceptance criteria

- [x] No heading element contains a price on any storefront template; one `<h1>` per page (test + crawl of home, category, product, search, cart).
- [x] All product lists print the title as `<h4>`; the outline test passes for product, category, home.
- [x] The product info column has no `<ul>`; screenshots at 360/768/1280 px reviewed.
- [x] `npm test`, `npm run verify` green; package rebuilt.
