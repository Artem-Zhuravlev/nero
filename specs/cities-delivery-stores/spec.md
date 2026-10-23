# SPEC-032 — Cities, Delivery and Pickup Info, Stores, Order Tracking

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-23
**Refines:** SPEC-024 REQ-MOY-022, 050, 054, 061 · **Depends on:** SPEC-012, SPEC-018, SPEC-031

## Problem

The reference shows per-city delivery options and dates, pickup availability and a stores list; core has none of this.

## Purpose

A city selector with a data model that feeds delivery/pickup information, a stores page and order tracking.

## Scope

Extension `ocs_city`: cities, delivery/pickup rules per city, stores (pickup points), city prompt, product-page delivery block,
stores page, order-tracking page. Information only; real shipping quotes stay in shipping extensions (SPEC-011/018).

## Out of scope

Live carrier APIs (SPEC-018), geo-IP detection, inventory sync from offline stores.

## Functional requirements

- **REQ-CTY-001** — Admin manages cities (name per language, sort, default); visitor city stored in cookie; default city used until chosen.
- **REQ-CTY-002** — Header prompt "Is your city X?" with confirm/other; selector in a popup (`OCS.modal`) with search.
- **REQ-CTY-010** — Delivery rules per city: methods (pickup, post office, courier), price, free-delivery threshold, estimated
  days or a "tomorrow" rule with cut-off time; texts per language.
- **REQ-CTY-011** — Product-page delivery and payment block next to the buy button: the methods above for the current city,
  payment methods text, warranty and return terms (settings text).
- **REQ-CTY-020** — Stores/pickup points per city (name, address, hours, phone, map link, coordinates); stores page lists them
  filtered by city.
- **REQ-CTY-021** — Product availability per pickup point from stock-per-location data when SPEC-012 provides it; else a single
  store-level availability; label "pick up in N minutes" configurable.
- **REQ-CTY-030** — Order tracking page: order number + phone or e-mail -> current status and history (read-only, rate-limited).

## Non-functional requirements

- **REQ-CTY-040** — City data cached; block adds one query per product page (REQ-MOY-081). uk/ru/en, PHP 7.4, switchable.
- **REQ-CTY-041** — Tracking never reveals data without matching second factor; generic message on mismatch.

## Data requirements

Tables `oc_ocs_city`, `oc_ocs_city_description`, `oc_ocs_city_delivery`, `oc_ocs_store`, `oc_ocs_store_stock` (optional);
settings `module_ocs_city_*`.

## Edge cases

- Cookie blocked -> default city. City without rules -> delivery block hidden.
- Product with no stock data -> availability line omitted.

## Acceptance criteria

- [ ] Changing the city changes delivery lines on the product page without reload.
- [ ] Tracking returns status only with matching phone/e-mail.
