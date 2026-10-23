# PLAN — SPEC-025 Quick Order, Pre-order, Back-in-stock

## Approach

Three extensions of type `module` built from one shared library `system/library/ocs/orderrequest.php` (validation, order
creation, notification). Buttons are injected into theme slots by Twig includes in the theme's category/product
overrides (SPEC-023) and by a `view` event for other listings. Order creation uses `catalog/model/checkout/order`.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Order creation | Core order model | Keeps totals, history, stock hooks consistent |
| Pre-order storage | Own table + optional order | Pre-order must not touch stock |
| Notifications | Core `Mail` + SPEC-018 hook for SMS later | No new dependency |

## Affected files / directories

- `custom/extensions/ocs_quick_order|ocs_preorder|ocs_back_in_stock/`, `system/library/ocs/orderrequest.php`,
  theme template includes, `custom/db/migrations/` per extension.

## Risks

- Option-level stock semantics differ per store -> option rules live in one library method with tests.
