# PLAN — SPEC-073 Product Gallery Stickers Top Right

## Approach

In `product.twig` drop the `.ocs-slot-tl` div of `.ocs-gallery` and loop `ocs_stickers.tl` then `ocs_stickers.tr` inside the existing `.ocs-slot-tr`.

## Affected files

- `custom/theme/ocs/template/product/product.twig`, `dist/ocs-theme-<VERSION>.ocmod.zip`, `specs/README.md`

## Risks

- A product with both `tl` and `tr` stickers shows them stacked in one corner; intended.
- The `bl` slot is unchanged and may still touch the thumbnail column.
