# PLAN — SPEC-032 Cities, Delivery, Stores, Tracking

## Approach

Library `Ocs\City` (current city, rules, stores) is the single source for header prompt, product block and stores page. Product
block is injected through a view event into the `#ocs-buy-slot` area or its own slot. Tracking page is a catalog controller on top
of `account/order` data models.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Scope of delivery data | Informational | Quotes need carrier integrations (SPEC-018) |
| Per-store stock | Optional table | OpenCart has no multi-location stock |

## Affected files / directories

- `custom/extensions/ocs_city/`, `system/library/ocs/city.php`, migrations.

## Risks

- Promising per-store stock without data -> label hidden unless data exists.
