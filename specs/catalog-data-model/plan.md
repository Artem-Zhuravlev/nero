# PLAN — SPEC-012 Catalogue Data Model

## Approach

Content stays in the DB (runtime data); structure and rules live in git. All writes to catalogue
tables go through core admin models via a headless bootstrap (`scripts/lib/php/AdminBoot.php`)
so derived tables and descriptions stay correct. Extra data lives in `oc_ocs_*` tables and
is injected by events.

## Decisions

| Decision | Choice | Why |
| -------- | ------ | --- |
| Writes | core admin models via bootstrap | category path closure, multi-table consistency |
| Extra fields | side tables + events | core tables untouched, no OCMOD |
| Codes | mapping table | no code column in core |
| Import format | one JSON/CSV schema | diffable, round-trippable |
| Product uniqueness | `model` | only mandatory unique-ish business field in core |

## Affected files

`custom/catalog/**`, `scripts/catalog-*.php`, `scripts/lib/php/AdminBoot.php`,
`custom/extensions/ocs_catalog_ext/**`.

## Risks

- Headless admin bootstrap needs session/user stubs (`user->hasPermission` not used by models).
- `noindex` defaults (=1) can hide new products from search engines silently -> REQ-CAT-012.
