# PLAN — SPEC-024 Storefront Functionality Reference

## Approach

This spec is a decision catalogue. Work happens in three steps: (1) the owner marks each `[NEW]` requirement as
accept / defer / reject and may change priorities; (2) accepted requirements are grouped into the owner specs below,
each created from `specs/_template/` as `draft` and approved separately; (3) implementation follows the normal
SPEC-first flow per owner spec, P1 first. The brief's own modules (section A) are scheduled before the `[NEW]` ones.

## Proposed owner specs (numbers are proposals, assigned when each spec is created)

| Proposed | Topic | Covers |
| -------- | ----- | ------ |
| SPEC-025 | Quick order, pre-order, back-in-stock | brief modules 1, 2, 14 (REQ-MOY-013) |
| SPEC-026 | AJAX cart and availability states | brief module 3 (REQ-MOY-013) |
| SPEC-027 | Mega menu, category wall, side category menu | brief modules 5, 6, 8 (REQ-MOY-010) |
| SPEC-028 | AJAX search with history and popular queries | brief module 7 (REQ-MOY-011) |
| SPEC-029 | Stickers | brief module 9 (REQ-MOY-012) |
| SPEC-030 | Recently viewed | brief module 10 (REQ-MOY-014) |
| SPEC-031 | Header, footer, announcement bar, mobile shell | REQ-MOY-020, 021, 023, 024, 025, 060 |
| SPEC-032 | Cities, delivery/pickup info, stores, order tracking | REQ-MOY-022, 050, 054, 061 |
| SPEC-033 | Home blocks: slider, brands, category carousels, newsletter, SEO text | REQ-MOY-030..034 |
| SPEC-034 | Category listing: sort, load more, filter chrome, card details | REQ-MOY-040..044 |
| SPEC-035 | Product page: tabs, key attributes, Q&A, accessories, similar | REQ-MOY-051..053, 055 |
| SPEC-036 | Credit block and trade-in request | REQ-MOY-056, 057 |

The earlier proposal in SPEC-023's plan (SPEC-024..029 for the brief's modules) is superseded by this table.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Form of this spec | Catalogue + mapping, no code | The reference is far bigger than one change; approval per feature is the point |
| Bought vs built | OCFilter, Simple bought; everything else our modules | Brief |
| Shared city data | One library `Ocs\City` owned by SPEC-032 | Avoids duplicated city logic in header, delivery, stores |
| Home/category/product blocks | Module per block, theme provides slots | Matches SPEC-023 slot approach, lets clients drop blocks |
| Reference fidelity | Functional idea only | Branding/text/images are not ours to copy |

## Affected files / directories

- `specs/storefront-reference/` (this spec), `specs/README.md` (row), `specs/theme-design/plan.md` (follow-up list pointer).
- No `custom/` changes.

## Risks

- Scope explosion (12 owner specs) -> priorities P1/P2/P3 and owner sign-off per requirement before any spec is opened.
- Reference observed on a narrow viewport -> desktop-only blocks may be missing; re-check desktop before approving owner specs.
- Per-city stock/delivery is not native to OpenCart -> SPEC-032 must decide a data model before promising REQ-MOY-054.
