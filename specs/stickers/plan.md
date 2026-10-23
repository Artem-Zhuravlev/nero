# PLAN — SPEC-029 Stickers

## Approach

A library `Ocs\Stickers` resolves stickers for a set of product ids (manual + rule) in one pass and returns per-position lists.
A `view` event (SPEC-007) on listing and product templates adds `product.stickers`; the theme overrides render them into
the existing slots. Admin: list + form, product-form tab by event.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Data injection | View event on `product/*` templates | No OCMOD on theme templates (REQ-OCM-040) |
| Rules | Fixed rule types, not free SQL | Safe and testable |

## Affected files / directories

- `custom/extensions/ocs_stickers/`, `system/library/ocs/stickers.php`, theme slot markup in category/product overrides.

## Risks

- Other listing templates still lack slots until their override exists (SPEC-023 follow-up) -> module ships a CSS-only fallback overlay for them.
