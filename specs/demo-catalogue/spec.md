# SPEC-041 — Demo Catalogue in the Theme Package

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-26
**Refines:** SPEC-040 (REQ-PKG-009: "never creates catalogue data" is replaced by this spec), SPEC-012 REQ-CAT-003

## Problem

A colleague who installs the theme into an empty or near-empty store sees blank category pages, no product cards, no stickers,
no filters, no reviews: nothing to judge the theme or its 25 modules with. The stock OpenCart demo data is not guaranteed to be
there and is not designed to exercise this theme (specials, out-of-stock, pre-order, options, stickers, reviews, accessories).

## Purpose

An opt-in, reversible "Demo catalogue" action on the Setup page that fills the store with a coherent electronics catalogue with
local images, built through core admin models, and that can be removed again without touching the shop's own data.

## Scope

- Data file `custom/catalog/demo/catalogue.json` (packaged in the payload) and generated placeholder images under
  `image/catalog/ocs-demo/`.
- `Ocs\Setup\DemoCatalogue` (PHP) driven from the admin Setup controller; buttons "Demo catalogue" and "Remove demo catalogue".
- Content: 6 top-level categories with 2-3 sub-categories each, 8 manufacturers, ~36 products, product images (main + 2-4 gallery),
  attributes and attribute groups, options (colour, memory) on selected products, specials and discounts, reviews, related
  products and accessories, stickers (new, hit, sale), product states for the modules (in stock, out of stock, pre-order,
  back-in-stock), a few information pages, 3 cities with delivery methods and 2 pick-up stores, texts in uk-ua, ru-ru and en-gb
  applied to every installed language.

## Out of scope

Customers, orders, payments, real photos or brand logos, price lists of real brands, bulk import tooling, multi-store demo data.

## Functional requirements

- **REQ-DCAT-001** — The Setup page offers "Demo catalogue" (create) and "Remove demo catalogue"; neither runs implicitly,
  both need the modify permission and a POST.
- **REQ-DCAT-002** — Data is created through core admin models (`catalog/category`, `catalog/manufacturer`, `catalog/attribute*`,
  `catalog/option`, `catalog/product`, `catalog/review`, `catalog/information`) so SEO URLs, descriptions per language and
  store links follow core rules; OCS tables (stickers, accessories, cities, stores) go through the Setup helpers.
- **REQ-DCAT-003** — Every created entity is recorded (table, id) in the setting `ocs_demo_catalogue`; products get model
  `OCSDEMO-NNN`, SEO keywords the prefix `demo-`. Removal deletes exactly the recorded rows (through the same models) and the
  image folder `image/catalog/ocs-demo/`, and never anything else.
- **REQ-DCAT-004** — Idempotent: a second "create" adds nothing when the record exists; a partial earlier run is completed.
- **REQ-DCAT-005** — Texts are provided for uk-ua, ru-ru and en-gb in the data file and applied to each installed language
  (fallback en-gb), so the store does not break with other language sets.
- **REQ-DCAT-006** — Images are small placeholder PNG files generated at pack time (no copyrighted pictures, no network),
  total under 2 MB, stored in the package under `upload/image/catalog/ocs-demo/`.
- **REQ-DCAT-007** — The catalogue exercises the theme: products with special and old price, with sticker, out of stock, pre-order,
  with options, with a gallery of 4+ images, with 3+ attributes, with reviews, in a category 3 levels deep, and enough products
  in one category (>= 16) for pagination and filters.
- **REQ-DCAT-008** — The existing "Demo content" (home blocks) keeps working and, when the demo catalogue exists, its tabbed shelf
  and category icons point at demo categories.
- **REQ-DCAT-009** — The packer includes the data and images, fails when an image referenced by the data is missing, and the
  secrets scan still passes.
- **REQ-DCAT-010** — `README-INSTALL.md` documents the buttons and warns that removal deletes only demo-marked rows.

## Non-functional requirements

- **REQ-DCAT-020** — The action completes within 60 s on a default PHP configuration (batching in AJAX steps if needed).
- **REQ-DCAT-021** — Works on stock OpenCart/ocStore 3.0.3.x with the PHP 7.1+ language level.
- **REQ-DCAT-022** — The package stays under 5 MB.

## Data requirements

`custom/catalog/demo/catalogue.json` (versioned, human-editable), generated `image/catalog/ocs-demo/**`, setting
`ocs_demo_catalogue` (JSON id registry). No schema changes.

## Interfaces

Admin routes `extension/ocs/setup/run` with actions `catalogue` and `catalogue_remove`; CLI generator of images
`scripts/gen-demo-images.mjs` (run by the packer).

## Edge cases

- Store already has products in the same categories -> demo entities are added beside them, never merged or renamed.
- A demo product edited by the shop owner -> removal still deletes it (documented); a "keep edited" option is not offered.
- A language is missing from the data -> en-gb texts are used.
- Removal after the owner deleted some demo rows -> missing rows are skipped.
- A model error -> the action stops, reports the failing entity, leaves the registry consistent for a retry.

## Acceptance criteria

- [x] On the clean test store, "Demo catalogue" creates the catalogue; home, category (paginated), product (gallery, options,
      reviews, sticker, special price), search and compare pages show demo data without PHP errors.
- [x] "Remove demo catalogue" leaves the store's other products, categories and settings unchanged (row counts equal to before).
- [x] Re-running create/remove is safe.
- [x] Packer, tests and `npm run verify` green; zip under 5 MB.
