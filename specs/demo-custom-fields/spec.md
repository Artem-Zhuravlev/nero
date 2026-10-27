# SPEC-068 — Demo Data Covers Every Custom Field

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-041 (REQ-DCAT-003, 004, 007), SPEC-040 REQ-PKG-009

## Problem

The admin buttons "Demo content" and "Demo catalogue" fill only core fields plus stickers (`hit`) and accessories. Everything the theme and its
modules added later stays empty on a demo store: product videos, questions, review pros/cons, contacts and messenger links, product-page info
blocks, top menu, benefits, credit plans, trade-in and pre-order texts, SEO text, menu banners, subscribers and requests. The theme cannot be tested
fully without typing all of it by hand.

## Purpose

One press of the existing demo buttons fills every custom field of the project with realistic data (uk/ru/en), so each theme block and each
admin list of the modules shows content. Removal returns the store to its previous state.

## Scope

Extends `custom/catalog/demo/catalogue.json`, `Ocs\Setup\DemoCatalogue` and `Ocs\Setup\DemoSeed`. Fields covered:

- **Product:** YouTube video (`ocs_product_video`), questions with/without answer and a pending one (`ocs_question`), review pros/cons
  (`ocs_review_extra`), stickers `hit`/`top`/`preorder` by manual assignment (rule stickers `new`/`sale`/`out` follow from data), accessories (more
  products), SKU/MPN/UPC/EAN/location/dimensions/weight/minimum/points, quantity discounts (`product_discount`), related, extra categories.
- **Category / brand / information:** menu icon, popular flag, mega-menu banner image + link (`ocs_menu_category`) also for sub-categories,
  category and brand descriptions, meta fields, information pages for the top menu and footer.
- **Theme settings:** `theme_ocs_contact` (address, hours, phones per language), `theme_ocs_contact_links` (messengers + social, every icon kind),
  `theme_ocs_info_blocks` (delivery, returns, payment, ...), `theme_ocs_top_menu` (pages, contacts, blog, sale), `ocs_show_sku`, `ocs_show_stock`.
- **Module settings:** `ocs_benefits` items, `ocs_credit` plans + texts, `ocs_tradein` categories + texts, `ocs_preorder` texts,
  `ocs_newsletter` texts, `ocs_ajax_search` popular queries, `ocs_seo_text`, `ocs_shell` announcement + dates, `ocs_brand_strip`, `ocs_mega_menu`
  brands, `ocs_category_wall`, `ocs_viewed`, `ocs_quick_order`; modules switched on.
- **Records for admin lists:** subscribers (`ocs_subscriber`), back-in-stock subscriptions, pre-order requests, trade-in leads.

## Out of scope

Real customers/orders, uploaded trade-in photos, blog posts (separate content spec), new custom fields, schema changes, storefront code changes.

## Functional requirements

- **REQ-DCF-001** — The existing buttons ("Demo content", "Demo catalogue") fill all fields listed in Scope; no new button.
- **REQ-DCF-002** — Per product the data file carries `video`, `questions[]`, `sku/mpn/upc/ean/location`, `discounts[]`, `stickers[]`; reviews
  carry `pros` and `cons`; at least 8 products have a video, 12 have questions (answered and not), 10 reviews have pros/cons, 5 have a quantity discount.
- **REQ-DCF-003** — Every sticker (`new`, `hit`, `sale`, `top`, `preorder`, `out`) is visible on at least one demo product.
- **REQ-DCF-004** — Theme and module settings are written only where the current value is empty (the owner's values are never overwritten); each
  written key is recorded in the registry.
- **REQ-DCF-005** — Texts exist for uk-ua, ru-ru, en-gb and fall back to en-gb for other languages (as REQ-DCAT-005).
- **REQ-DCF-006** — Records for admin lists use an `@example.test` e-mail and a `+380000000xxx` phone, are registered, and appear in the
  corresponding admin lists.
- **REQ-DCF-007** — Idempotent (REQ-DCAT-004): a second run adds no duplicates and completes a partial earlier run; a store that ran the old
  version is topped up on the next press.
- **REQ-DCF-008** — "Remove demo catalogue" deletes the recorded rows and empties the settings it wrote (those still equal to the demo value),
  leaving the owner's own data untouched.
- **REQ-DCF-009** — Core models are used where they exist; OCS tables are written through the Setup helpers and only when the table exists
  (a module missing in the store is skipped, not an error).

## Non-functional requirements

- **REQ-DCF-020** — Total run stays under 60 s (REQ-DCAT-020); PHP 7.1 syntax.
- **REQ-DCF-021** — No real personal data, brands or phones; video ids are neutral public clips or clearly fake.
- **REQ-DCF-022** — Package stays under 5 MB; `README-INSTALL.md` lists what the demo fills.

## Data requirements

Registry keys in `ocs_demo_catalogue`: `video:<code>`, `question:<n>`, `subscriber:<n>`, `backinstock:<n>`, `preorder:<n>`, `lead:<n>`,
`setting:<code>:<key>`, `menu:<categoryCode>`. No schema changes.

## Interfaces

Admin `extension/ocs/setup/run` actions `demo`, `catalogue`, `catalogue_remove` (unchanged).

## Edge cases

- Module extension not installed -> its fields are skipped with a log line.
- Owner already filled contacts -> not overwritten, not removed on cleanup.
- Fewer languages installed -> only those get texts.

## Acceptance criteria

- [x] After "Demo catalogue" + "Demo content", every item in Scope is visible on the storefront or in its admin list.
- [x] A product page shows video, accessories, questions, reviews with pros/cons, discounts, info blocks, credit block, SKU.
- [x] Footer/header show contacts, messengers, top menu; mega menu shows banners.
- [x] Second run changes nothing; removal restores row counts and settings.
- [x] `npm run verify` and `npm run test:php` green; theme archive rebuilt.
