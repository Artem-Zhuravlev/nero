# PLAN — SPEC-011 Orders and Checkout

## Approach

Keep the core flow intact and constrain it: declare statuses/transitions as data, gate status
changes with an admin/model event, standardise provider callbacks, and protect the path with a
scripted scenario test. Customisations stay in themes/events/extensions.

## Decisions

| Decision | Choice | Why |
| -------- | ------ | --- |
| Status source of truth | JSON + migration | reproducible; ids stable |
| Pending counted as processing | no (override core default) | stock reserved only when confirmed/paid |
| Transition rules | event on `sale/order` history + API path | no core edit |
| Checkout protocol | untouched | high regression risk |
| Provider callbacks | signed, idempotent, unique transaction id | duplicate notifications are normal |

## Affected files

`custom/orders/*.json`, `custom/extensions/ocs_order_rules/**` (library-type), payment/shipping
extensions (own specs), `scripts/scenario-checkout.mjs`, `docs/settings-registry.md` generator.

## Risks

- Event-based transition guard may miss non-admin paths (callbacks) -> library function used by both.
