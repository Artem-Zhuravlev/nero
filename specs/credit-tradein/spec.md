# SPEC-036 — Instalments Block and Trade-in Request

**Status:** superseded
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-23
**Refines:** SPEC-024 REQ-MOY-056, 057 · **Depends on:** SPEC-011, SPEC-018, SPEC-035

## Problem

Electronics clients want to show instalment payments and a trade-in offer on product pages.

## Purpose

Optional display blocks: instalment calculator and a trade-in request form. No payment processing here.

## Scope

Extension `ocs_credit` (calculator, bank-partner settings) and `ocs_tradein` (banner + request form).

## Out of scope

Bank APIs and payment flow (SPEC-011/018), trade-in valuation, loyalty.

## Functional requirements

- **REQ-CRD-001** — Instalment block on the product page: monthly payment for configured plans (months, rate/commission, minimum
  and maximum price) of enabled bank partners; updates with option price changes; link "Details" opens `OCS.modal` with terms text.
- **REQ-CRD-002** — Plans are display values from settings; the block states that final terms are set by the bank; hidden when price is outside any plan.
- **REQ-CRD-003** — Selecting a plan can pre-fill the checkout/quick-order comment (no order logic change).
- **REQ-CRD-010** — Trade-in banner on configured categories with a button opening a form (device description, phone, e-mail, photos
  optional); stored as a lead in admin and e-mailed to the manager. No valuation (P3).

## Non-functional requirements

- **REQ-CRD-020** — Forms use CSRF token, rate limit and `OCS.modal`; uploads limited to images <= 2 MB x 3.
- **REQ-CRD-021** — uk/ru/en, PHP 7.4, each block switchable and off by default (REQ-MOY-001).

## Data requirements

Table `oc_ocs_tradein_lead`; settings `module_ocs_credit_*`, `module_ocs_tradein_*`; uploads in storage outside the docroot.

## Edge cases

- Zero-rate plan -> payment = price / months, rounded with the store currency rules. Currency switched -> recalculated.

## Acceptance criteria

- [ ] Calculator values equal the formula for each plan and currency; hidden outside min/max price.
- [ ] Trade-in lead appears in admin and sends a notification.
