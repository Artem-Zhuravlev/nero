# SPEC-116 — Admin review form: pros, cons and admin reply

**Status:** implemented
**Owner:** Zhuravlev Artem
**Last updated:** 2026-10-29

## Problem

SPEC-049 stores the pros and cons of a review (`oc_ocs_review_extra`) but the admin review form only prints them read-only, and only when a row exists, so they cannot be edited or added for a review (e.g. one created in admin). There is also no way for the shop to answer a customer review.

## Purpose

Make "Pros" and "Cons" editable fields and add an "Admin reply" field to the admin review form (Catalog -> Reviews); show the reply under the review on the product page.

## Scope

- Admin review form (add and edit): three fields under the review text — Pros, Cons, Admin reply.
- Storage in the existing satellite table `oc_ocs_review_extra` (new column `reply`).
- Storefront review list (theme `ocs`): the reply block under the review.

## Out of scope

- Email/notification to the customer about a reply.
- Reply history, several replies, replies by other roles.
- Changing the storefront review form (pros/cons input stays as in SPEC-049).
- Review list columns/filters in admin.

## Functional requirements

- **REQ-REV-030** — The admin review form shows editable single-line inputs "Pros" and "Cons" (max 255 characters) and a textarea "Admin reply" (max 2000 characters) directly after the review text, on add and edit, filled with stored values.
- **REQ-REV-031** — Saving a review (add/edit) stores pros, cons and reply in `oc_ocs_review_extra`; text is tag-stripped and trimmed (`\Ocs\ReviewExtra::clean`, reply keeps line breaks). If all three are empty the row is deleted.
- **REQ-REV-032** — The product page review list shows a non-empty admin reply under the review text (label "Shop reply"/"Відповідь магазину"), escaped, line breaks kept.
- **REQ-REV-033** — The read-only pros/cons output of SPEC-049 REQ-REV-005 is replaced by the editable fields; deleting a review still deletes its extra row.

## Non-functional requirements

- **REQ-REV-034** — Every read/write of the table is guarded: a store that has not run the migration still opens the admin form and saves reviews (fields hidden or save skipped, error logged, never thrown to the admin).
- **REQ-REV-035** — Admin strings in en-gb and uk-ua (and ru-ru if present for the extension); storefront strings in all three catalog languages. Output escaped (Twig autoescape is off).
- **REQ-REV-036** — Core files untouched; the OCMOD stays idempotent and anchored to core `review_form.twig`.

## Data requirements

- Migration `ocs_product_extras/004_review_reply.php`: idempotent `ALTER TABLE oc_ocs_review_extra ADD COLUMN reply TEXT` (only if missing); registers the new admin save events. Rollback: unregister the events, keep the column.

## Interfaces

- OCMOD `ocs_review_extra` keeps the `{{ ocs_review_extra }}` insertion after the text textarea (now an editable form block with the `ocs_extras_present`-style marker not needed: fields are always posted).
- Admin events: `admin/view/catalog/review_form/before` (render), `admin/model/catalog/review/addReview/after` and `.../editReview/after` (save from `$this->request->post`; add uses the returned id), `deleteReview/before` (cleanup).
- Catalog event `catalog/view/product/review/before` adds `reply` next to pros/cons; `review.twig` prints it.

## Edge cases

- Review without an extra row -> empty fields are shown.
- Migration not run -> no fields, saving still works.
- Reply on a disabled review -> stored, shown only when the review is published.
- Over-long input -> cut to the limit.

## Acceptance criteria

- [ ] Admin form shows Pros, Cons, Admin reply, on add and edit.
- [ ] Values saved and reloaded; clearing all three removes the row.
- [ ] Reply appears under the review on the product page.
- [ ] Form works with the migration not yet applied.
