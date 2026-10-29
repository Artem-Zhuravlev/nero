# PLAN — SPEC-116 Admin review form: pros, cons, admin reply

## Approach

Extend the `ocs_product_extras` admin events: `beforeReviewForm` renders editable inputs (always, with values from the extra row if any), new `afterSaveReview` writes the row. Migration 004 adds `reply`. Catalog event passes `reply` to `review.twig`.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Storage | Add `reply` column to `ocs_review_extra` | Same satellite pattern (SPEC-049); not altering core `oc_review` |
| Save hook | Admin `addReview/after` + `editReview/after` events | No core edit; post data read from request |
| Form markup | Event-rendered HTML via existing OCMOD variable | Existing anchor, no new OCMOD |

## Affected files / directories

- `custom/extensions/ocs_product_extras/db/004_review_reply.php`
- `custom/extensions/ocs_product_extras/upload/{admin,catalog}/controller/.../events.php`, `system/library/ocs/reviewextra.php`
- admin/catalog language files, `custom/theme/ocs/template/product/review.twig`, CSS if needed
- `custom/ocmod/ocs_review_extra.ocmod.xml` (header comment only, if unchanged anchor)
- `scripts/test/php/*` tests for `ReviewExtra`
- `dist/ocs-theme-<VERSION>.ocmod.zip`, `custom/VERSION`

## Risks

- A reviewer posts the form with the migration unapplied -> guarded queries.
- `addReview/after` event gets the new id as `$output`; verify against `core/upload/admin/model/catalog/review.php`.
