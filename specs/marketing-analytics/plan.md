# PLAN — SPEC-017 Marketing, Analytics, Returns

## Approach

One extension `ocs_tags` (consent, tag loader, data layer, adapters) plus small rule extensions (coupon guard, returns rules, mail filter).
Data and rules in JSON; everything consent-gated by default.

## Decisions

| Decision | Choice | Why |
| -------- | ------ | --- |
| Tag loading | single declarative loader | no pasted snippets, auditable |
| Event model | GA4-style data layer | vendor-neutral adapters |
| `tracking` cookie | withheld without consent | cookie is personal data |
| Affiliates | off by default | no business case |
| CAPI | out of scope | needs separate legal/technical spec |

## Affected files

`custom/marketing/*`, `custom/extensions/ocs_tags/**`, `custom/extensions/ocs_promo_guard/**`, `custom/orders/returns.json`, `docs/cookies.md`, `docs/kpis.md`.

## Risks

- Cookie withholding relies on event timing after core sets it; verify header output at implementation.
- Theme JS must emit `add_to_cart` from core AJAX responses; couples to `common.js` behaviour (SPEC-006 HOOKS.md).
