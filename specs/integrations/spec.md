# SPEC-018 — External Integrations Architecture (payments, delivery, feeds, webhooks)

**Status:** draft
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-15
**Refines:** SPEC-004, SPEC-011, SPEC-014 · Provider-agnostic: no vendor is chosen here.

## Platform facts (verified in `core/upload/`)

1. Payment/shipping/total contracts: SPEC-004 and `docs/ocstore-reference.md` §4. Payment methods are offered by `getMethod()`; the order is created
   by `checkout/confirm` **before** payment; the paid status is set through `addOrderHistory` (cod example: `confirm()` calls it directly).
2. Core ships many provider extensions (Authorize.Net, PayPal family, Sagepay, Klarna, Liqpay, etc.) that are **not part of our product**; they stay
   uninstalled and hidden (SPEC-010 REQ-ACC-006).
3. Core HTTP client usage in extensions is ad hoc `curl` code per provider; there is no shared HTTP client, retry, or webhook framework.
4. Callbacks from providers hit storefront routes (`extension/payment/<code>/callback`) which run the full startup (session, store resolution,
   language), so they need no admin session.
5. Shipping quotes are computed on demand inside checkout requests (`getQuote`), so slow providers slow checkout directly.
6. Feeds: `extension/feed/*` (google_base, google_sitemap) are routes with their own status settings.

## Problem

Every integration brings secrets, network failure modes, duplicate notifications, rate limits and PII transfers. Written ad hoc, they cause lost
orders, double charges or leaked keys.

## Purpose

Define one architecture for outbound API clients, inbound webhooks, provider adapters (payment, delivery, feeds), credentials, sandboxing, observability
and the acceptance process for adding a provider.

## Scope

Shared client library, webhook receiver pattern, provider extension template, credential handling, testing with simulators, rollout/rollback of a provider.

## Out of scope

Choosing providers; per-provider field mappings (each provider gets `SPEC-0NN integration-<provider>`).

## Functional requirements

### Provider integration spec template

- **REQ-INT-001** — Every provider integration has its own spec created from `specs/_template/` plus mandatory sections: API version and docs URL, auth method,
  endpoints used, field mapping tables, status mapping (provider status -> order status, SPEC-011), error taxonomy, rate limits, idempotency keys, sandbox
  credentials policy, PII sent/received, fallback behaviour, kill switch.
- **REQ-INT-002** — A provider spec cannot be `approved` without a **simulator** plan (REQ-INT-050) and a rollback plan (REQ-INT-060).

### Outbound HTTP client (library `Ocs\Http\Client`)

- **REQ-INT-010** — The only way custom code calls external HTTP APIs (SPEC-009 REQ-QA-034): wraps curl with mandatory timeouts (connect 2 s, total 10 s default,
  overridable per call but <= 30 s), TLS verification on, redirect limit 0 by default, response size cap (2 MB), JSON helpers, User-Agent
  `OCS/<version>`, and never follows to non-HTTPS.
- **REQ-INT-011** — Retries: idempotent requests (GET, or POST with idempotency key) retry up to 2 times on connect errors/5xx/429 with jittered backoff
  (200 ms, 800 ms) and respect `Retry-After`; non-idempotent requests never retry automatically.
- **REQ-INT-012** — Circuit breaker per provider key (state in cache): after 5 consecutive failures the client fails fast for 60 s (returns an `Unavailable`
  result) and logs once; checkout paths treat `Unavailable` as "method not offered".
- **REQ-INT-013** — Logging: every call writes one JSON line to `ocs-http.log` (provider, method, host+path without query, status, duration ms, request id, error class),
  never headers/bodies; opt-in body logging only in `local` with secret scrubbing.
- **REQ-INT-014** — Time budget: the sum of external calls inside one storefront request must be <= 4 s (checkout) / 1.5 s (catalogue pages); the client tracks a
  per-request budget in the registry and refuses further calls when exhausted.

### Inbound webhooks / callbacks

- **REQ-INT-020** — Webhook routes follow `extension/payment|shipping|feed/<code>/callback` or `extension/ocs_integrations/<provider>/webhook`. The controller:
  (1) reads raw body once (`php://input`) and stores nothing before verification; (2) verifies signature/HMAC with constant-time comparison or IP allow-list +
  secret token (provider-dependent, documented in the provider spec); (3) validates timestamp skew (<= 5 min) if the provider offers it; (4) deduplicates by
  `(provider, event_id)` in `oc_ocs_webhook_event` (unique key) before acting; (5) acts in a try/catch; (6) responds with the provider-expected status/body quickly
  (< 3 s), doing slow work later via job (SPEC-014).
- **REQ-INT-021** — Table `oc_ocs_webhook_event(event_pk, provider, event_id, received_at, processed_at NULL, status ENUM('received','processed','failed','ignored'),
  payload_hash CHAR(64), error VARCHAR(255))`; payload bodies are stored only when the provider spec requires audit, encrypted with `config_encryption`, 90-day retention.
- **REQ-INT-022** — Replay tool `php scripts/webhook-replay.php <event_pk>` re-processes a stored failed event (idempotent); admin screen for the table is read-only.
- **REQ-INT-023** — Webhook endpoints are excluded from CSRF/session assumptions but still pass through core startup; they must not start customer sessions or set cookies.
- **REQ-INT-024** — Robots/WAF: callback paths are disallowed in robots, excluded from the throttles of SPEC-013 REQ-SEO-063 and SPEC-015, and listed in `docs/network.md` with source IPs.

### Payment adapters

- **REQ-INT-030** — Payment extension template (`new-extension.mjs --template payment-redirect|payment-onsite|payment-offline`) implements SPEC-004 REQ-EXT-041 plus
  SPEC-011 REQ-ORD-040: states `created -> pending_payment -> paid | failed | cancelled | expired | refunded` mapped to order statuses in the provider spec; amount/currency
  check against order totals with tolerance 0.01 in the order currency; partial payments are not supported unless the provider spec says so.
- **REQ-INT-031** — Reconciliation job (SPEC-014 jobs): for orders in pending-payment older than 30 min, query the provider for status (rate-limited) and apply the same
  transitions as the webhook; orders unpaid after the provider's expiry move to `Expired (14)`; report mismatches daily to `OPS_ALERT_EMAIL`.
- **REQ-INT-032** — Refunds are initiated from admin by a role-restricted action in the provider extension (never automatic), call the provider with an idempotency key,
  write `Refunded (11)` history with comment key and provider refund id, and store it in `oc_ocs_<provider>_transaction`.
- **REQ-INT-033** — Card data never touches our servers: redirect/hosted fields only; PCI scope stays SAQ A; verify greps for `cc_number|cvv|card_number` in custom code (error).
- **REQ-INT-034** — Test mode: `payment_<code>_test` switches endpoints and keys; the checkout shows a visible banner in test mode and test mode is refused when
  `APP_ENV=production` (migration/validate check).

### Delivery adapters

- **REQ-INT-040** — Delivery providers are `shipping` extensions with: city/warehouse lookups cached 24 h (library with provider dictionary tables
  `oc_ocs_<provider>_city`, `..._point` refreshed by a nightly job), quote caching (SPEC-011 REQ-ORD-043), tracking-number storage on the order
  (`oc_ocs_shipment(order_id, provider, tracking, label_url, status, date_*)`), and status polling job mapping provider states to Shipped/Complete.
- **REQ-INT-041** — Address data sent to providers is minimised to what the API needs; consent/privacy notice mentions processors (SPEC-015 REQ-CUS-030 purposes).
- **REQ-INT-042** — Pickup-point selection UI is a theme-compatible component loaded only on checkout; it degrades to a text field if the provider script fails.
- **REQ-INT-043** — Shipment creation (labels) is an admin action or a status-triggered event handler guarded by a setting; failures are retried by job and visible in
  an admin list; the order is never blocked by a failing provider call.

### Feeds and exports

- **REQ-INT-045** — Product feeds (marketplaces, ad platforms) are `feed` extensions generating XML/CSV from cached catalogue data with per-feed settings (categories included,
  price rounding, availability rules), streamed, cached 1 h, protected by an unguessable token in the URL path when not public; they use the same indexability/status
  rules as the storefront (disabled products excluded).

### Credentials, sandboxes, simulators

- **REQ-INT-050** — Each provider has a **simulator** (`scripts/sim/<provider>.mjs`, small HTTP server reproducing success, decline, timeout, 5xx, duplicate webhook, bad
  signature, delayed webhook) used by automated checks and by the checkout scenario (SPEC-011 REQ-ORD-061). Provider base URLs are settings so tests point at the simulator.
- **REQ-INT-051** — Credentials are settings entered in admin per environment (never in git/.env except bootstrap values documented in `.env.example` with empty values);
  masked in forms; rotated by runbook `credentials-rotation` (SPEC-014); an expiry/rotation date setting triggers a warning in the health endpoint 14 days before.
- **REQ-INT-052** — Production and sandbox credentials are never present in the same database; migrations refuse to set production-looking keys when `APP_ENV != production`
  (provider specs define the pattern).

### Rollout and rollback

- **REQ-INT-060** — Each provider has a kill switch (`<type>_<code>_status`) and a documented degradation: payment hidden -> alternative methods remain; delivery hidden ->
  fallback flat method (`ocs_flat_fallback`) with a notice; feed disabled. Switching it is a settings migration or admin action covered by the runbook.
- **REQ-INT-061** — Go-live checklist per provider (recorded in its spec): sandbox end-to-end scenario, duplicate webhook test, timeout test, refund test, reconciliation test,
  security review (signature, secrets, PII), monitoring dashboard/alert, legal (contracts, privacy), rollback rehearsal.

## Non-functional requirements

- **REQ-INT-070** — Library overhead per call < 2 ms excluding network; memory < 2 MB.
- **REQ-INT-071** — A provider outage must not raise the storefront error rate: catalogue pages perform no provider calls.
- **REQ-INT-072** — All provider-facing identifiers (order id sent as reference) are stable and include an environment prefix to avoid collisions between environments
  (`<env>-<order_id>`).

## Data requirements

`oc_ocs_webhook_event`, `oc_ocs_shipment`, per-provider tables (`..._transaction`, dictionaries); settings per provider.

## Edge cases

- Webhook arrives for an unknown order -> 200 acknowledged and `ignored` (avoid provider retry storms) + alert if repeated.
- Provider changes signature scheme -> verification fails closed; events stored as `failed`; kill switch available.
- Clock skew on server -> timestamp check logs a distinct error; health endpoint reports time drift if an NTP check is available.
- Duplicate order references across environments -> prefixed (REQ-INT-072).
- Provider returns amount in minor units/other currency -> adapter converts explicitly and tests cover rounding.

## Acceptance criteria

- [ ] `Ocs\Http\Client` unit tests: timeouts, retries, circuit breaker, budget, no body logging.
- [ ] Webhook pattern test with the simulator: bad signature rejected, duplicate deduplicated, failed event replayable.
- [ ] Payment scenario against simulator: paid, declined, timeout, delayed webhook, reconciliation.
- [ ] Delivery scenario: quote cache, provider down -> method hidden + fallback.
- [ ] Verify rejects direct `curl_init` outside the client and card-data keywords.
