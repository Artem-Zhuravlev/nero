# SPEC-017 — Marketing, Analytics, Consent-aware Tracking and Returns

**Status:** draft
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-15
**Refines:** SPEC-011, SPEC-015, SPEC-013

## Platform facts (verified in `core/upload/`)

1. Marketing campaigns: `oc_marketing` (tracking `code`, `clicks`); storefront `startup/startup` sets cookie `tracking` (1000 days) when `?tracking=<code>`
   is in the URL and increments `clicks`; orders store `marketing_id` and `tracking`.
2. Coupons: `oc_coupon`, `_category`, `_product`, `_history`; order total extension `total/coupon`; admin `marketing/coupon`. Vouchers: `oc_voucher*`, total `voucher`.
3. Affiliates: `config_affiliate_approval|auto|commission|id`; account route `account/affiliate`, `account/tracking`; `oc_customer_affiliate`;
   commission stored on `oc_order` (`affiliate_id`, `commission`).
4. Analytics: extension type `analytics` (core example `google`), setting `config_google_analytics` (legacy raw snippet field), `analytics` array
   rendered by `common/header.twig` (`{% for analytic in analytics %}`); advertise extension `google` (remarketing events registered in
   `oc_event`, reference §7).
5. Returns: `oc_return` (+ `_action`, `_history`, `_reason`, `_status`), storefront `account/return` & `information/…`, admin `sale/return`;
   settings `config_return_id` (terms page, default 0), `config_return_status_id` (2); events `activity_return_add`, `statistics_return_add`, `admin_mail_return`.
6. Mailing: admin `marketing/contact` (bulk mail to customers/groups/newsletter) uses the store Mail transport.

## Problem

Marketing features rely on cookies and third-party scripts, so they interact with consent law, page speed and data accuracy. Core
pastes raw analytics snippets into settings, sets a 1000-day cookie without consent, and has no scheduled-campaign discipline.

## Purpose

Define how campaigns, coupons, affiliate tracking, analytics/ads tags and returns are implemented consent-aware and reproducibly.

## Scope

Tag management, consent gating, campaign tracking, coupon governance, affiliate decision, bulk mail rules, returns workflow, KPI events.

## Out of scope

Ad account structure, creative, price strategy, loyalty programmes, marketplace feeds (own specs).

## Functional requirements

### Consent-aware tracking

- **REQ-MKT-001** — All non-essential scripts and cookies (analytics, ads, pixels, `tracking` cookie, chat widgets, A/B tools) are loaded through one
  mechanism: extension `ocs_tags` (type `analytics`). Tags are declared in `custom/marketing/tags.json`:
  `{ "id", "category": "analytics|marketing|functional", "consent": "analytics|marketing", "src|inline", "env": ["production"], "async": true }`.
  Pasting snippets into `config_google_analytics`, html modules, template files or admin "Custom code" fields is forbidden (lint: `<script` in
  html content is already rejected by SPEC-016 sanitiser; `config_google_analytics` is set empty by migration).
- **REQ-MKT-002** — `ocs_tags` renders nothing until the visitor's consent (cookie `ocs_consent` value signed with HMAC; JSON of granted
  categories, 12-month expiry) grants the tag's category. Essential cookies (`OCSESSID`, `language`, `currency`, `ocs_consent`) are documented
  in `docs/cookies.md` (generated from `tags.json` + core list).
- **REQ-MKT-003** — Consent banner (theme + `ocs_tags`): equal-prominence Accept/Reject/Settings, no pre-ticked boxes, remembers choice, re-ask on
  consent-version bump (`custom/customers/consents.json`), records server-side only a hash-based event for logged-in customers (SPEC-015).
- **REQ-MKT-004** — The core `tracking` cookie is set by `startup/startup` before any extension code can intervene, so without consent it is
  removed afterwards: `ocs_tags` registers a handler on `catalog/controller/common/header/before` that expires the `tracking` cookie (via
  `setcookie` in the past) when marketing consent is absent, and re-sets it on consent. The click counter in `oc_marketing` still increments
  (aggregate, non-personal). Orders keep `marketing_id` only if the cookie exists. Verify the cookie header at implementation time.
- **REQ-MKT-005** — Tag performance: all external tags `async`/`defer`, injected after `DOMContentLoaded` or `requestIdleCallback`; the total third-party
  JS weight budget is 150 KB; Core Web Vitals regressions after adding a tag block its release (manual Lighthouse check recorded).

### Data layer and events

- **REQ-MKT-010** — A single JS data layer `window.OCS.dataLayer` (array of events) is populated server-side per page by `ocs_tags` view events:
  `page_view`, `view_item` (product), `view_item_list` (category/search), `add_to_cart` (from cart AJAX response hook in theme JS), `begin_checkout`,
  `purchase` (success page; value/currency/transaction_id=order_id/items). Item fields: `item_id`=model, `item_name`, `price`, `quantity`, `item_brand`,
  `item_category`. Prices are in the order currency.
- **REQ-MKT-011** — `purchase` fires once per order (guard: `localStorage` + server-side flag `oc_ocs_tag_purchase(order_id)`), only for orders in a
  paid/processing status per SPEC-011, never for the Pending-payment status.
- **REQ-MKT-012** — Vendor adapters (GA4, Meta pixel, Google Ads) are small files under `custom/extensions/ocs_tags/upload/catalog/view/javascript/ocs/tags/`
  mapping the data layer to vendor calls; adding a vendor needs a tag entry + adapter + docs row, no template edits.
- **REQ-MKT-013** — Server-side conversion APIs (e.g. Meta CAPI) are out of scope; if added they need their own spec with consent proof and hashed identifiers only.

### Campaigns and coupons

- **REQ-MKT-020** — Marketing campaigns (`oc_marketing`) codes follow `utm`-compatible naming `<channel>-<yyyymm>-<slug>`; created in admin; a doctor
  warns on duplicates. UTM parameters are kept in sessionStorage by theme JS (consent-gated) and attached to orders through a hidden field only with
  marketing consent.
- **REQ-MKT-021** — Coupon governance: every coupon has code (uppercase `[A-Z0-9-]{4,20}`), `uses_total`, `uses_customer`, `date_start/end`, minimum total,
  and is created through admin; a nightly job (SPEC-014) flags coupons without end date or with `uses_total=0` older than 90 days. Percent coupons > 30%
  require the Administrator role (permission split: event on `marketing/coupon` save for non-admins).
- **REQ-MKT-022** — Coupon abuse: brute-force protection — more than 10 failed coupon applications per session/IP in 10 minutes blocks coupon input for 30 minutes
  (event on `controller/extension/total/coupon/coupon/before`, cache counters).
- **REQ-MKT-023** — Free-shipping and fee/discount rules beyond core coupons are `total` extensions (SPEC-004) with documented sort orders (SPEC-011).

### Affiliates

- **REQ-MKT-030** — Decision recorded in the store-config spec: affiliate programme **off** by default (`config_affiliate_approval=1`, account route hidden through theme and
  permissions) unless a business case exists; if on, commission rules, cookie lifetime (not 1000 days: 30 days) and payout process are specified before enabling.

### Bulk mail

- **REQ-MKT-040** — Marketing e-mail only to customers with a current `marketing_email` consent (SPEC-015 `oc_ocs_consent`); `marketing/contact` is wrapped by an event
  that filters recipients accordingly and appends the unsubscribe link; sends are batched (100 per run) by a job, not in a single admin request.
- **REQ-MKT-041** — Transactional and marketing mail are separate streams (From address/Sender header differ) per deployment checklist.

### Returns

- **REQ-MKT-050** — Return reasons, actions and statuses are data in `custom/orders/returns.json` and applied by migration through admin models; `config_return_id`
  points to the `returns` information page (SPEC-016) and `config_return_status_id` to the initial status.
- **REQ-MKT-051** — Return eligibility window (e.g. 14 days from delivery) is enforced by a `controller/account/return/add/before`-style validation hook
  reading `order_history` (first Shipped/Complete date); rule parameters in `custom/orders/returns.json`.
- **REQ-MKT-052** — Return status changes notify customers via core `admin_mail_return`; refunds are manual (finance process) and recorded as order history
  (status Refunded 11) with comment key `ocs:admin:<id>` (SPEC-011 REQ-ORD-004).
- **REQ-MKT-053** — Returns data is personal data: declared for DSR (SPEC-014), retained 5 years (fiscal), then anonymised.

### KPIs

- **REQ-MKT-060** — A fixed KPI set is documented in `docs/kpis.md` with definitions and SQL (conversion = orders in processing/complete statuses / sessions
  from analytics; AOV; revenue by channel via `marketing_id`; coupon usage; return rate). Admin dashboard widgets are core's; extra reports are `report` extensions.

## Non-functional requirements

- **REQ-MKT-070** — With consent denied, the page makes zero third-party requests (verified by smoke test counting external hosts).
- **REQ-MKT-071** — `ocs_tags` adds < 5 ms server time and < 10 KB of inline JS.

## Data requirements

`oc_ocs_tag_purchase(order_id PK, tag_id, date_added)`; JSON sources `custom/marketing/*`, `custom/orders/returns.json`; core marketing/coupon/return tables.

## Edge cases

- Consent withdrawn after a purchase tag fired: nothing retroactive; cookies for categories are removed client-side on withdrawal.
- Ad blockers break vendor scripts: checkout must not depend on any tag (REQ-MKT-005; smoke test with all tags blocked).
- Multi-currency: `purchase.currency` is the order currency; values never converted client-side.
- Visitors arriving with `?tracking=` and no consent: click counted, cookie withheld, no attribution.
- Preview/staging environments: tags marked `env: production` never load elsewhere.

## Acceptance criteria

- [ ] With no consent cookie, the home page makes no third-party requests and sets only essential cookies.
- [ ] After granting analytics consent the declared tags load once; withdrawal removes them on next load.
- [ ] `purchase` event fires exactly once for a completed test order, with correct value.
- [ ] Coupon brute-force test blocks after 10 failures.
- [ ] Returns migration creates reasons/statuses idempotently; ineligible return is rejected with a localised message.
