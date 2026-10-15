# PLAN — SPEC-016 Content and Blog

## Approach

Fixed pages and layout composition are code (JSON + HTML files → migrations through admin models); editor-created content
is runtime data with sanitisation, completeness rules and doctor scripts. One extension `ocs_content` (sanitiser, link placeholders,
preview tokens, noindex automation).

## Decisions

| Decision | Choice | Why |
| -------- | ------ | --- |
| Sanitiser | core HTMLPurifier | already shipped, no new dependency |
| Layout placement | JSON + migration | environments stay identical |
| Preview | signed token event | no core change |
| Legal text | files in git | versioned and reviewable |

## Affected files

`custom/content/**`, `custom/extensions/ocs_content/**`, `scripts/content-export.mjs`, `scripts/content-doctor.php`, `scripts/layout-check.php`.

## Risks

- HTMLPurifier config for iframe allow-list is fiddly; fixtures required.
- Admin editor may re-introduce stripped markup; sanitiser runs server-side on every save.
