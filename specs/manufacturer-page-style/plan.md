# PLAN — SPEC-075 Brands Page Visual Redesign

## Approach

Replace the `row`/`col-sm-3` markup in `manufacturer_list.twig` with a `ul`-free grid (`.ocs-brand-grid` > `a.ocs-brand-item`) and a chip nav (`.ocs-brand-index`). Keep the `data-brand-letter`, `.ocs-brand-group`, `.ocs-brand-item` hooks so the SPEC-074 script needs no change. New CSS in a `components` layer block at the end of the stylesheet, replacing the SPEC-074 rule's spacing.

## Affected files

- `custom/theme/ocs/template/product/manufacturer_list.twig`, `stylesheet/stylesheet.css`, `dist/ocs-theme-<VERSION>.ocmod.zip`, `specs/README.md`

## Risks

- The filter script hides items via `hidden`; grid `display` must not override `[hidden]` (add `[hidden]{display:none}` for the new classes).
