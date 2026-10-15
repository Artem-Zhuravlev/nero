# SPEC-011 — Orders and Checkout Architecture

**Status:** draft
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-15
**Refines:** SPEC-001, SPEC-004 (payment/shipping/total contracts)

## Platform facts (verified in `core/upload/`)

1. **Checkout is an AJAX accordion on one page** (`checkout/checkout`): sections are separate
   controllers returning HTML fragments/JSON — `checkout/login`, `register`, `guest`,
   `payment_address`, `shipping_address`, `guest_shipping`, `shipping_method`, `payment_method`,
   `confirm`, `success`, `failure`, plus `checkout/cart`. Templates in
   `catalog/view/theme/default/template/checkout/*.twig`; client logic in the template scripts and
   `catalog/view/javascript/common.js`.
2. State lives in `$this->session->data`, keys seen in checkout code: `shipping_address`,
   `payment_address`, `guest`, `shipping_method(s)`, `payment_method(s)`, `vouchers`, `coupon`,
   `reward`, `comment`, `currency`, `order_id`, `last_order_id` (+ `account`, `agree`, `error`,
   `success`).
3. `checkout/confirm` builds the order data and calls `model_checkout_order->addOrder($data)`,
   storing `session->data['order_id']`; totals are collected from `extension` rows of type `total`
   whose `total_<code>_status` is on, sorted by `total_<code>_sort_order`; payment methods from type
   `payment` filtered by `payment_<code>_status` and `getMethod()`.
4. `model/checkout/order.php` public API: `addOrder`, `editOrder`, `deleteOrder`, `getOrder`,
   `getOrderProducts`, `getOrderOptions`, `getOrderVouchers`, `getOrderTotals`,
   **`addOrderHistory($order_id, $order_status_id, $comment='', $notify=false, $override=false)`**.
   `addOrderHistory` is the single status-change gate: it runs fraud extensions (type `fraud`) when the
   new status is in `config_processing_status` ∪ `config_complete_status`, calls each `total`
   extension's `confirm()` (coupon/voucher/reward), subtracts stock when entering a
   processing/complete status, restocks and releases coupons/rewards when leaving them, writes
   `oc_order_history`, and fires events (`mail_order_*`, `statistics_order_history`, `activity_order_add`).
5. Default order statuses (id: en / uk): 1 Pending/Очікування, 2 Processing/В обробці, 3 Shipped/
   Доставлено, 5 Complete/Завершено, 7 Canceled/Скасовано, 8 Denied/Повернення, 9 Canceled Reversal,
   10 Failed/Помилкове, 11 Refunded, 12 Reversed/Змінене, 13 Chargeback, 14 Expired/Не актуальне,
   15 Processed/Оброблене, 16 Voided/Анульоване. Settings: `config_order_status_id=1` (initial),
   `config_processing_status=["5","1","2","12","3"]`, `config_complete_status=["5","3"]` — note the
   shipped defaults include 1 (Pending) in "processing", which means an order is counted
   (stock subtracted) as soon as it is Pending. That is a decision point (REQ-ORD-020).
6. `oc_order` stores a full snapshot (customer, payment/shipping address text + ids, method
   name/code, totals, currency code/value, ip, user agent, language) — orders never depend on later
   edits of customers/addresses.
7. Relevant settings: `config_checkout_guest`, `config_checkout_id` (terms page), `config_account_id`,
   `config_stock_checkout`, `config_stock_warning`, `config_cart_weight`, `config_customer_group_*`,
   `config_login_attempts`, `config_captcha*`, `config_invoice_prefix`.

## Problem

Money flows through code we do not own, built from session state and AJAX fragments. Any
extension (payment, shipping, total) or template change that breaks a hidden contract silently
loses orders. We need explicit invariants, status semantics, and a test strategy.

## Purpose

Define the order lifecycle for this store, the invariants extensions must keep, how checkout may
be customised, and the checks protecting the money path.

## Scope

Order status model and mapping, checkout customisation rules, payment/shipping/total extension
integration rules (beyond SPEC-004), stock handling, notifications, invoicing, data retention,
test plan for the checkout path.

## Out of scope

Concrete payment/delivery providers and their credentials, fiscalisation, accounting integration —
each its own spec.

## Functional requirements

### Status model

- **REQ-ORD-001** — The store's order statuses are declared in `custom/orders/statuses.json` (id, names per
  language, role: `initial|processing|complete|cancelled|failed|refund`, `notify` default flag) and
  mirrored by a migration into `oc_order_status` (names) and the settings `config_order_status_id`,
  `config_processing_status`, `config_complete_status`, `config_fraud_status_id`. Ids of core
  statuses are never changed or reused; new statuses get ids >= 100.
- **REQ-ORD-002** — Status transitions allowed by business rules are listed in
  `custom/orders/transitions.json` (from -> [to]). Admin status changes outside the table are
  rejected by an admin model event on `sale/order` history add (message from language file);
  programmatic changes (payment callbacks) are validated the same way and logged when rejected.
- **REQ-ORD-003** — Status changes always go through `model_checkout_order->addOrderHistory` (catalog) or the
  admin API call that ends in it. Direct `UPDATE oc_order SET order_status_id` is forbidden
  (lint rule: string `order SET order_status_id` outside core).
- **REQ-ORD-004** — Every status change carries a comment key identifying its origin
  (`ocs:payment:<code>`, `ocs:admin:<user_id>`, `ocs:system`); never customer-visible text with internal
  detail.

### Stock and "processing" semantics

- **REQ-ORD-020** — Decision recorded in `statuses.json`: whether `Pending (1)` belongs to
  `config_processing_status`. Default for this project: **Pending is NOT processing** (stock is
  reserved only when payment/confirmation moves the order to Processing/Complete), implemented
  by the migration; changing it needs a spec (it changes stock behaviour and abandoned-order
  handling).
- **REQ-ORD-021** — Abandoned orders (status 0 "missing orders", created by `confirm` and never paid)
  are visible only through the admin "missing orders" filter and are purged by an operational job
  after 30 days (SPEC-014), never counted in reports.
- **REQ-ORD-022** — Stock: `config_stock_checkout=0` (no checkout of out-of-stock) unless a spec
  says otherwise; `subtract` stays on products; no extension writes `oc_product.quantity` directly.

### Checkout customisation rules

- **REQ-ORD-030** — Allowed: template overrides (SPEC-006 REQ-THM-051), view events that add variables,
  extra fields via **custom fields** (`oc_custom_field`, core feature) configured by migration,
  an additional `total` extension, new payment/shipping extensions, `controller/checkout/*/after`
  events that read state.
- **REQ-ORD-031** — Forbidden: replacing the AJAX flow with a different protocol, removing a
  checkout section controller, writing the core session keys of fact 2 outside their owner
  controllers/extensions, calling `addOrder` from custom code (only `checkout/confirm` creates orders).
- **REQ-ORD-032** — Guest checkout policy is a setting (`config_checkout_guest`) set by migration per the
  store-config spec; terms page via `config_checkout_id`; both documented in the settings
  registry (`docs/settings-registry.md`, REQ-ORD-060).
- **REQ-ORD-033** — Required fields (telephone, address lines, postcode) follow core's per-country
  validation; project-specific rules (e.g. phone format) are implemented as a `controller/checkout/
  guest/before` style event that returns a JSON error in core's format (`{"error":{"field":"msg"}}`)
  using language strings.

### Payment, shipping, total integration

- **REQ-ORD-040** — Payment extensions that redirect to a provider (hosted page) set
  `payment_<code>_order_status_id` to a *pending-payment* status; the callback endpoint
  (`catalog/controller/extension/payment/<code>/callback`) verifies the provider signature,
  is **idempotent** (same notification twice -> one status change), checks amount and currency
  against `getOrder()` totals, and then calls `addOrderHistory` with the paid status.
- **REQ-ORD-041** — Callback endpoints never trust `order_id` from the request without the signature,
  respond with the provider's expected acknowledgement, log with `[ocs_<code>]` and store the provider
  transaction id in `oc_ocs_<code>_transaction` (unique index on provider id).
- **REQ-ORD-042** — Secrets for providers (API keys) are settings stored in DB but entered only in
  admin; they are masked in the form (`********`) and never logged or exported by the settings
  registry; test mode flag `payment_<code>_test` exists for each provider.
- **REQ-ORD-043** — Shipping quote extensions that call external APIs cache results for 10 minutes
  per cart hash, time out in <= 3 s and degrade to "method unavailable" (empty array) on failure.
- **REQ-ORD-044** — Total extensions are deterministic pure functions of cart/session (SPEC-004
  REQ-EXT-043) and their `sort_order` values are documented in the registry; core totals keep
  core order (verified in `install/opencart.sql`): sub_total 1, reward 2, shipping 3, coupon 4, tax 5,
  credit 7, voucher 8, total 9; a new total uses a free slot (e.g. 6 for a fee before voucher, or
  a fractional-free integer between neighbours) and never 9 or 1.

### Notifications and documents

- **REQ-ORD-050** — Customer/admin order e-mails are core's (`mail/order`, `mail/order/alert` events);
  customising them is done with theme templates under `mail/order*.twig` and language files, not
  by new mail code; SMTP settings from `.env` via migration (`config_mail_*`, SPEC-014).
- **REQ-ORD-051** — Invoice numbering uses core (`config_invoice_prefix`, `invoice_no`); the prefix
  format `INV-<year>-00` from the installer is replaced by the store's format via migration
  before the first real order.

### Registry and tests

- **REQ-ORD-060** — `docs/settings-registry.md` lists every checkout-relevant setting key, its value
  per environment, owner spec and rationale; generated from migrations by a script (no hand edits).
- **REQ-ORD-061** — Test plan (levels per SPEC-001 REQ-ARCH-150): unit tests for libraries (totals,
  fee calculators, signature verification); HTTP smoke for cart/checkout pages; a scripted
  **checkout scenario** (`scripts/scenario-checkout.mjs`) executed in `local` against a seeded
  product that: adds to cart, goes through guest checkout with a test payment method (`cod`-like
  `ocs_test_payment`), asserts an order row, history row, totals sum = displayed total, stock
  decrement per REQ-ORD-020, and e-mail event fired (mail transport set to file).
- **REQ-ORD-062** — The scenario is mandatory before releasing any change to checkout, payment,
  shipping, total, cart, theme `checkout/*` or `common/cart` (release checklist, SPEC-009 REQ-QA-052).

## Non-functional requirements

- **REQ-ORD-070** — Order creation must not take more than one DB round trip per product beyond
  core's behaviour (no N+1 added by extensions).
- **REQ-ORD-071** — PII in orders is handled per SPEC-014 retention rules; logs never contain
  full addresses, phone numbers, e-mails or payment data.

## Data requirements

Core order tables (`order*`, `order_status`, `order_history`, `order_total`, `custom_field*`);
new `oc_ocs_<provider>_transaction` per payment provider; `custom/orders/*.json` as data sources.

## Edge cases

- Customer double-clicks confirm -> core creates one order per `session order_id`; extensions must
  tolerate repeated `confirm()` (REQ-ORD-040 idempotence).
- Provider callback arrives before the customer returns -> `addOrderHistory` runs first; the
  success page is just a view.
- Currency changes between cart and confirm -> order stores `currency_code/value` snapshot.
- Product price changed after order -> order lines keep their price.
- Language switched mid-checkout -> order stores `language_id`; mail uses it.

## Acceptance criteria

- [ ] Statuses and `config_*_status` settings match `statuses.json` after migrate (test).
- [ ] Rejected transition is blocked in admin with a localised message.
- [ ] Checkout scenario passes on a seeded store, including stock and totals assertions.
- [ ] Payment callback idempotence test: duplicate notification -> one history row.
- [ ] Lint finds a direct `order_status_id` update in a fixture.
