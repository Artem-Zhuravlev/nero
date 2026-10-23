# SPEC-025 — Quick Order, Pre-order, Back-in-stock

**Status:** in-progress
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-23
**Refines:** SPEC-024 REQ-MOY-013 · **Brief:** modules 1, 2, 14 · **Depends on:** SPEC-023 (`OCS.modal`, `#ocs-buy-slot`), SPEC-004, SPEC-011

## Problem

Customers who do not want the full checkout, or who want a product that is out of stock, have no fast path.

## Purpose

Three small modules sharing one popup style: one-click order, pre-order for unavailable products, subscription to
availability.

## Scope

Extensions `ocs_quick_order`, `ocs_preorder`, `ocs_back_in_stock`: storefront buttons (card, category, product page,
quick view), popup forms, server validation, admin settings and lists, notifications.

## Out of scope

Payment, shipping choice (quick order uses default methods), SMS gateways (SPEC-018), the full checkout (Simple).

## Functional requirements

### Quick order (module 1)

- **REQ-QOR-001** — "Quick order" button in `#ocs-buy-slot`, on cards and in quick view; opens `OCS.modal` with fields
  name, phone, e-mail, comment; each field enabled/required by setting.
- **REQ-QOR-002** — The form carries product id, quantity and chosen options; the server recalculates price and totals,
  never trusting posted prices.
- **REQ-QOR-003** — Server checks stock, required options and field formats; errors are returned per field.
- **REQ-QOR-004** — Creates a real order via the catalog order model (customer, products, options, prices, total) with a
  configurable initial status; e-mail notification to admin (and customer when e-mail given).

### Pre-order (module 2)

- **REQ-QOR-010** — For products with stock <= 0 the "Buy" button is replaced by "Pre-order" (global switch, per-product
  override); button and customer message texts configurable per language.
- **REQ-QOR-011** — Form: name, phone, e-mail, comment, selected options; optional expected arrival date shown to the
  customer (per-product field).
- **REQ-QOR-012** — Saved as a request in an own admin list (`oc_ocs_preorder`) with status; optionally also creates an order
  with a dedicated status. It never decrements stock and never becomes a normal purchase automatically.
- **REQ-QOR-013** — Admin and customer notifications; option stock is respected (an option value that is available is not
  offered as pre-order).

### Back in stock (module 14)

- **REQ-QOR-020** — Subscription form (e-mail, product, option) on out-of-stock products; duplicates per e-mail+product+option
  are rejected; basic spam protection (honeypot + rate limit by IP).
- **REQ-QOR-021** — A job (SPEC-014) finds subscriptions whose product/option is back in stock and sends one e-mail, then
  marks them notified.
- **REQ-QOR-022** — Admin list with filters, delete, export CSV.

## Non-functional requirements

- **REQ-QOR-030** — Popups use `OCS.modal`; uk/ru/en strings; PHP 7.4; each feature switchable (REQ-MOY-001).
- **REQ-QOR-031** — No customer data in logs; forms protected by token against CSRF; rate limit 5 requests/min/IP.

## Data requirements

Tables `oc_ocs_preorder`, `oc_ocs_back_in_stock`; settings `module_ocs_quick_order_*`, `module_ocs_preorder_*`,
`module_ocs_back_in_stock_*`; product attribute for arrival date (own table, no core change).

## Edge cases

- Product with required options not chosen -> form blocked with message.
- Product disabled between page load and submit -> error, no order.
- Same e-mail subscribes to two options -> two subscriptions.

## Acceptance criteria

- [ ] Quick order creates an order visible in admin with correct totals and options.
- [ ] Pre-order never changes stock; admin sees request list.
- [ ] Subscription e-mail is sent once after restock.
