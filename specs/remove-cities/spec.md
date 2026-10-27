# SPEC-052 — Remove the Cities Feature

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Supersedes:** SPEC-032 (cities, delivery and pickup info, stores, order tracking)

## Problem

The store does not need per-city behaviour. The header shows the visitor's city ("Львів") with a prompt
"Is your city X?" and a city selector popup, which confuses visitors of a single-region shop.

## Purpose

Remove the whole city functionality from the storefront, admin, database and tests, as was done for the quick view (SPEC-044).

## Scope

Extension `ocs_city` entirely: city selector, header button and prompt, header links "Stores" and "Track order",
product-page delivery block, stores page, tracking page, admin screens, events, settings, tables, demo cities,
theme.js/CSS code and tests that belong to it.

## Out of scope

Customer address fields (`city` in checkout/account forms) and order `shipping_city`/`payment_city` — they are core address data, not this feature.
Real shipping methods (SPEC-011/018).

## Functional requirements

- **REQ-RCT-001** — The header shows no city button, no city prompt and no Stores/Track links; no city cookie is read or set.
- **REQ-RCT-002** — The product page has no delivery-by-city block (`ocs-delivery-wrap` slot removed).
- **REQ-RCT-003** — Extension `ocs_city` is removed from `custom/extensions/`, its routes, events (`ocs_city_header`, `ocs_city_product`),
  permissions and settings (`module_ocs_city_*`) are unregistered by a new idempotent migration.
- **REQ-RCT-004** — The migration drops the `ocs_city*` and `ocs_store*` tables.
- **REQ-RCT-005** — Demo catalogue no longer seeds cities/stores; theme.js and stylesheet contain no city/store/track/delivery code or rules.

## Non-functional requirements

- **REQ-RCT-010** — `npm run verify` and `npm test` stay green; tests of the removed feature are deleted, not skipped.
- **REQ-RCT-011** — Theme package is rebuilt (`npm run pack:theme`).

## Data requirements

New migration `0008_remove_cities.php`: unregister events/extension/permission/settings, drop 7 tables (`DROP TABLE IF EXISTS`). Irreversible
for data (restore the SPEC-032 commit and its migration to bring the feature back).

## Edge cases

- Migration run twice or on a store where the extension was never installed -> no error.
- Product page without the slot -> layout of the buy block unchanged.

## Acceptance criteria

- [ ] Header has no city / stores / track elements.
- [ ] Product page has no delivery block.
- [ ] `grep -ri ocs_city custom scripts` finds nothing except the migration.
- [ ] `npm run verify`, `npm test`, `npm run test:php` green; archive rebuilt.
