# SPEC-115 — Admin product form: no Accessories tab, video field on the Data tab

**Status:** implemented
**Owner:** Zhuravlev Artem
**Last updated:** 2026-10-29

## Problem

The admin product form has an "Accessories" tab (SPEC-035) holding the accessory ID list and, since SPEC-048, the YouTube video field. Accessories are no longer edited by hand; the video field is hidden on an unrelated tab.

## Purpose

Remove accessory editing from the admin product form and show the YouTube video field on the "Data" tab right under the product model.

## Scope

- Admin product form only (`ocs_product_extras` admin event, template, language, OCMOD).

## Out of scope

- Storefront "Accessories" carousel, the `module_ocs_product_extras_accessories` setting, the `oc_ocs_product_accessory` table and demo data stay (the carousel keeps showing already stored links).
- Video storage, parsing and gallery output (SPEC-048) are unchanged.

## Functional requirements

- **REQ-PRD-120** — The admin product form has no "Accessories" tab and no accessory ID field.
- **REQ-PRD-121** — Saving a product does not read or change `ocs_product_accessory` links; deleting a product still removes its links.
- **REQ-PRD-122** — The "YouTube video" field is rendered on the "Data" tab directly after the "Model" field, with the same help text and validation error as before.
- **REQ-PRD-123** — Saving the video behaves as in SPEC-048 (URL/id parsed, empty removes, invalid reported on next load).

## Non-functional requirements

- **REQ-PRD-124** — Handlers never throw to the admin user; the OCMOD stays idempotent and anchored to core `product_form.twig`.

## Data requirements

None (no schema or settings change).

## Interfaces

- OCMOD `ocs_product_extras_form`: drops the tab link/pane insertions, inserts `{{ ocs_video_field }}` after the model form-group in `#tab-data`.
- Admin event `beforeProductForm` provides `ocs_video_field`; `save()` keeps the `ocs_extras_present` marker (rendered with the field).

## Edge cases

- Module status disabled -> no field, nothing saved.
- Product form re-shown after an invalid video -> value kept, error under the field.

## Acceptance criteria

- [ ] No "Аксесуари" tab in the product form.
- [ ] Video field appears under Model on the Data tab and saves/loads.
- [ ] Existing accessory links survive a product save.
