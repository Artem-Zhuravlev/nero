# PLAN — SPEC-015 Customer Accounts

## Approach

Two extensions: `ocs_customer_security` (throttling, enumeration resistance, reset expiry, honeypot,
hash upgrade once approved) and `ocs_customer_privacy` (consent, export/delete/claim). Groups and fields are
data applied through core admin models.

## Decisions

| Decision | Choice | Why |
| -------- | ------ | --- |
| Hash upgrade | blocked on explicit OCMOD approval | core login compares legacy columns itself |
| Identifier | e-mail | core behaviour |
| Consent storage | own table with hashes | auditable without raw IPs |
| Deletion | anonymise, keep accounting facts | retention duties |

## Affected files

`custom/customers/*.json`, `custom/extensions/ocs_customer_security/**`, `custom/extensions/ocs_customer_privacy/**`,
theme overrides `account/*`, `common/footer` (consent links).

## Risks

- Hash upgrade design requires patching `system/library/cart/customer.php` (restricted by SPEC-007) → needs an explicit decision.
- Core validation messages are fixed; custom rules must reuse its error structure.
