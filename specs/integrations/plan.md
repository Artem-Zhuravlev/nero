# PLAN — SPEC-018 Integrations

## Approach

Shared library extension `ocs_integrations` (HTTP client, circuit breaker, webhook base controller, signature helpers, transaction/shipment tables) and
extension templates for payment/delivery/feed. Provider specs reuse them and add only mapping and simulator.

## Decisions

| Decision | Choice | Why |
| -------- | ------ | --- |
| HTTP | one wrapper over curl | uniform timeouts, logging, breaker |
| Webhook dedupe | DB unique key on provider event id | providers retry |
| Reconciliation | scheduled pull in addition to webhooks | webhooks get lost |
| Simulators | in repo per provider | deterministic failure tests |
| Card data | never handled | PCI scope |

## Affected files

`custom/extensions/ocs_integrations/**`, `scripts/new-extension.mjs` templates, `scripts/sim/*`, `scripts/webhook-replay.php`, `docs/network.md`.

## Risks

- Per-request time budget needs shared state in the registry — implemented in the library constructor, tested.
- Provider docs vary; the provider-spec template forces explicit decisions early.
