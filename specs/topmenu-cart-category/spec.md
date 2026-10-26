# SPEC-050 — Top Menu from Settings, Cart Badge, Cart Page Redesign, Subcategory Carousel, Sort Dropdown

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-26
**Refines:** SPEC-031 (header shell), SPEC-039 (header counters), SPEC-026 (AJAX cart), SPEC-034 (category listing), SPEC-043 (templates), SPEC-047 (theme settings editors), SPEC-045 (headings), SPEC-044 (SKU switch)

## Problem

Review items 28-32:

28. The header shows information pages chosen by the client (a top-menu setting in the theme settings), and code-driven items: contacts, blog and sales ("Акції"); the sales item is highlighted with a colour. Reference: right side of the top bar, "Блог", "% Акції" (red), "Покупцям ⌄".
29. The cart badge in the header does not show the number of items (an empty blue dot). Root cause found: the badge CSS reads `attr(data-badge)` from the cart button, but the script writes `data-badge` on the wrapper `.ocs-head-cart`; `attr()` reads the element the pseudo-element belongs to, so the content is empty. (The wishlist and compare badges are on the same element and work.)
30. The cart page must follow the shop design: product image, name, options, quantity with update/remove icon buttons, price per unit, line total; the "Use coupon / estimate shipping / gift certificate" blocks as collapsed panels in the shop style; and **old prices struck through** when a product is on sale, as in the catalogue and the product card.
31. Subcategories on category pages are shown small, with category images, in a horizontal carousel (reference: a row of small image cards under the title).
32. Sorting is hidden under a dropdown; the row of sort buttons (chips) goes away.

## Scope

Theme `ocs`: `common/header.twig`, `checkout/cart.twig`, `product/category.twig`, stylesheet, `theme.js` (badge), theme settings form (top menu), `ocs_listing` (category view events), cart view event (old prices), tests.

## Out of scope

The checkout steps page, the mini cart panel (SPEC-026), mobile bottom bar, mega menu content, the blog module itself.

## Functional requirements

### Top menu (item 28)

- **REQ-TOP-001** — The theme settings form gets a "Top menu" fieldset: on/off, the title of the dropdown group (per language, default "For customers"), a list of **information pages** to show (checkbox per page from the store's information pages with a sort field),
  and three switches for the code-driven items: Contacts (`information/contact`), Blog (the blog home route of the installed blog module), Sales (`product/special`). Stored as `theme_ocs_top_menu`; saved values are sanitised (ids must exist, titles trimmed, <= 60 characters).
- **REQ-TOP-002** — Rendering in the utility bar, right side, before the language switch: Blog, **Sales** (icon + text, highlighted by the accent colour token `--sale`), and a dropdown button with the group title that lists the selected information pages and Contacts; when a switch is off or nothing is selected
  the item is not rendered; a menu with no items renders nothing. Titles of information pages come from the current language. Active page is marked (`aria-current`).
- **REQ-TOP-003** — On phones (< 768 px) the items stay in the utility bar as a horizontally scrollable row (no wrapping, 44 px targets); the dropdown opens as a full-width sheet. The existing store/track links of SPEC-032 and the old `ocs_shell` links are unchanged.

### Cart badge (item 29)

- **REQ-BDG-001** — The cart button shows the number of items (sum of quantities, vouchers included, as the panel counts them; "99+" above 99) and hides the badge at 0. The script writes `data-count`/`data-badge` on the element that carries the `::after` (the button), and re-renders on every change (add, remove, update, panel, other tab, page load).
  A test fails if a pseudo-element uses `attr()` of an attribute that its own element never gets.

### Cart page (item 30)

- **REQ-CRT-001** — `checkout/cart.twig` is restyled with the theme components: each line is a card/grid row (image, name link, options and recurring text, model when the SKU switch is on, quantity stepper with update and remove **icon** buttons, unit price, line total); the table remains a table on wide screens (`<table>` semantics kept for screen readers) and becomes stacked cards below 768 px.
  No Font Awesome, no `<h2>` price headings; core ids, field names (`quantity[key]`) and form actions are unchanged.
- **REQ-CRT-002** — For a product with a special price or a quantity discount the line shows the **regular price struck through** (`<s>`) beside the current price, both for the unit price and the line total; products without a reduction show only the current price. Old prices come from a view event that reads the regular product price once per line (tax rules as the cart uses) and never changes cart totals.
- **REQ-CRT-003** — The "Use coupon / estimate shipping and taxes / use gift certificate" blocks are collapsed `<details>` panels in the theme style (core JS and endpoints of those blocks keep working: they are loaded as before); totals in a summary box with the primary "Checkout" button and the secondary "Continue shopping"; the "weight" suffix stays in the heading as core prints it.

### Subcategories (item 31)

- **REQ-SUB-001** — On a category page the child categories are shown as a horizontal carousel (the SPEC-037 `ocs-carousel` component): small cards (image 64 px, name in two lines, optional product count), arrows from the container width, swipe on touch; the old chips and the "Refine search" lists are removed. Images come from the category image (resized, lazy), with a neutral icon when there is none.
  The block is not rendered when there are no children. Category links keep the SEO URLs.
- **REQ-SUB-002** — The data (image thumb) is added by an `ocs_listing` category view event; a failure of the image lookup leaves the cards without images and does not hide the block (guarded, lesson of SPEC-048).

### Sorting (item 32)

- **REQ-SRT-001** — The sort buttons row (`.ocs-sortchips`) is removed from category, search, manufacturer and special pages; sorting is the dropdown in the toolbar, which lists every sort option the listing supports (price low/high, newest, popular, reviews, name, rating). The current option is selected and changing it navigates as before.

## Non-functional requirements

- **REQ-TOP-020** — Settings read once; no extra queries beyond the information page titles (one query per request, cached by the core); output escaped (`Ocs\TopMenu` pure class, unit-tested).
- **REQ-CRT-020** — No behaviour change of cart add/update/remove/vouchers/coupon/shipping; no new library; heading rules of SPEC-045 hold; the cart page keeps working without JS.
- **REQ-QA-050** — For this spec the done criterion is a behavioural check of the touched pages on the test store (home, category, cart with and without items, product), not only text tests; new class names are checked against the whole stylesheet before use.

## Edge cases

- No blog module installed -> the Blog item is not rendered even when the switch is on.
- No products on sale -> the Sales item still links to the special page (empty-state of that page).
- Cart with a product that has options priced above the base price -> the old price keeps the option part, so the struck price is never below the current one.
- A category with one child -> carousel with one card, no arrows.
- Language without a translated group title -> default language title.

## Open decisions (default in bold)

1. Sales highlight colour: **a token `--sale` (red, AA on white)**; or a colour field in the settings.
2. Phones: the top menu as **a scrollable row in the utility bar**; or moved into the mobile catalogue panel.
3. Old price in the cart: **unit price and line total**; or unit price only.
4. Subcategory card: **image + name**; or image + name + product count.
5. Sorting: **dropdown only** (the toolbar select that exists); or a custom styled dropdown button with the current option shown.

## Implementation notes

- Defaults of the open decisions were used (approved 2026-10-26).
- The coupon / shipping / voucher panels keep the core Bootstrap collapse markup (`.panel-group` with `data-toggle="collapse"`) restyled as cards, not `<details>`: the core blocks and their AJAX are bound to those ids.
- On phones the cart row shows the line total (old price struck through, then the current one) and hides the unit price column; wide screens show both. The first mobile layout collided the two prices, found in the browser and fixed.
- The sort chips were removed from category, search, special and manufacturer pages; the toolbar dropdown already listed every option added by `ocs_listing` (verified: 7 options on a category page).
- Subcategory images come from the category image (resized to 128 px); the demo categories have no images, so the carousel shows the neutral icon; one demo category was given an image on the test store to check the thumb path.
- The Blog item is shown when the ocStore blog is on (`configblog_blog_menu`) and `controller/blog/latest.php` exists; it replaces the old blog dropdown only when the top menu has the Blog switch on.
- Not exercised in a logged-in admin: the top menu fieldset (rendered by Twig only; the saved value was written to the test store directly). The dropdown of information pages was not opened visually.
- Checked on the test store: header at 1024 px (Blog, red Sales, group dropdown, language), cart badge numbers (1 and 4), cart page at 1024 px and 375 px with two sale lines, category page (carousel, no chips, 7 sort options), 375 px page width without horizontal scroll.

## Acceptance criteria

- [x] The theme settings have a top menu editor; the chosen information pages, Contacts, Blog and Sales appear in the header; Sales is red; the dropdown title is per language.
- [x] The cart badge shows the item count after add, remove, update and page load; hidden at 0.
- [x] The cart page uses the theme design at 360/768/1280 px; sale products show a struck-through old price; coupon / shipping / voucher panels open and work.
- [x] A category page shows its subcategories as a small image carousel; no chips; sorting is a dropdown with all options.
- [x] Behavioural checks on the test store for the touched pages pass; touched-area tests pass; package rebuilt.
