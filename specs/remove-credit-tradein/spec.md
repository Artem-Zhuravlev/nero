# SPEC-087 — Remove Instalments and Trade-in

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-28
**Supersedes:** SPEC-036 (instalments and trade-in)

## Problem

The store will use third-party instalment and trade-in services (external widgets / payment methods). The built-in modules
`ocs_credit` (instalment calculator on the product page) and `ocs_tradein` (trade-in banner, request form, leads) duplicate them.

## Purpose

Remove both modules entirely from the storefront, admin, database, demo data and tests, following SPEC-044 and SPEC-052.

## Scope

Extensions `ocs_credit` and `ocs_tradein`: product-page instalment block and trade-in banner, trade-in banner on category pages,
trade-in request form/popup, admin screens, events, permissions, settings, the `ocs_tradein_lead` table, theme.js/CSS code,
demo settings and demo leads, tests.

## Out of scope

Real payment methods (SPEC-011/018) — third-party ones are installed separately.
The demo FAQ/info-block texts that merely mention instalments ("Can I pay in installments?", "Card, cash or installments.") — plain
content, not the feature; they stay.
Customer-account "credit cards" tiles (core feature).

## Functional requirements

- **REQ-RCI-001** — The product page has no instalment block and no trade-in banner; the category page has no trade-in banner.
- **REQ-RCI-002** — Extensions `ocs_credit` and `ocs_tradein` are removed from `custom/extensions/`; their events
  (`ocs_credit_product`, `ocs_tradein_product`, `ocs_tradein_category`), extension registrations, permissions and settings
  (`module_ocs_credit_*`, `module_ocs_tradein_*`) are unregistered by a new idempotent migration.
- **REQ-RCI-003** — The migration drops table `ocs_tradein_lead` (existing trade-in leads are lost).
- **REQ-RCI-004** — theme.js, stylesheet and `DESIGN.md` contain no `ocs-credit` / `ocs-tradein` code or rules; the quick-order
  button no longer appends an instalment note to the comment.
- **REQ-RCI-005** — Demo catalogue no longer seeds `module_ocs_credit*`, `module_ocs_tradein*` settings or trade-in leads.

## Non-functional requirements

- **REQ-RCI-010** — `npm run verify`, `npm test`, `npm run test:php` stay green; tests of the removed modules are deleted, not skipped.
- **REQ-RCI-011** — Theme package is rebuilt (`npm run pack:theme`).

## Data requirements

New migration `0012_remove_credit_tradein.php`: unregister events/extensions/permissions/settings, `DROP TABLE IF EXISTS ocs_tradein_lead`.
Irreversible for leads (restore the SPEC-036 commit to bring the feature back). Export leads first if they matter.

## Edge cases

- Migration run twice, or where the modules were never installed -> no error.
- Product page without the slots -> buy block layout unchanged.
- Quick-order button on a product page without OCS.credit -> unchanged behaviour.

## Acceptance criteria

- [ ] No instalment block / trade-in banner on product or category pages.
- [ ] `grep -ri "ocs_credit\|ocs_tradein\|ocs-credit\|ocs-tradein" custom scripts` finds only the migration.
- [ ] `npm run verify`, `npm test`, `npm run test:php` green; archive rebuilt.
