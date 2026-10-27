# PLAN — SPEC-074 Brand Search on Manufacturer List

## Approach

Add `<input id="ocs-brand-search">` to the template, mark brand links/groups with data attributes, and a small filter script in `theme.js`. Minimal CSS. Placeholder text via the existing language mechanism of the theme.

## Affected files

- `custom/theme/ocs/template/product/manufacturer_list.twig`, `javascript/theme.js`, `stylesheet/stylesheet.css`, `dist/ocs-theme-<VERSION>.ocmod.zip`, `specs/README.md`

## Risks

- Removing the `@generated` mark means the generator no longer refreshes this template.
