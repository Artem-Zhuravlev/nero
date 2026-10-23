# PLAN — SPEC-038 Modern Layout

## Approach

1. Split `stylesheet.css` into ordered sections inside cascade layers (the build concatenates, so one request remains). Bootstrap 3
   stays loaded but is wrapped with `@import … layer(reset)` from the header-injected `<link>` replaced by an inline `@layer` order
   declaration at the top of the theme stylesheet; where a core widget needs Bootstrap (dropdowns, tooltips, tabs) its rules remain.
2. Replace float/`col-*` structure in the theme overrides with grid containers; keep the old class names on the elements as hooks.
3. Rebuild in this order: shell + header, card + listing, home, category, product, footer, popups. Each step is checked in the
   browser pane at 375 and 1440 px.
4. Add static tests that guard the technique (layers, subgrid, container queries, no new `!important`, budgets, hooks).

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Bootstrap 3 | Keep, lowest layer | Core JS widgets and extension templates use its classes; removing it breaks them |
| Responsive tool | Container queries first, media queries only for the shell | Components move between listing, carousel and popup |
| Fallbacks | `@supports` blocks | Safe on older browsers without extra JS |
| Fonts | System stack; Inter stays optional | No download without permission |

## Affected files / directories

- `custom/theme/ocs/stylesheet/stylesheet.css`, `custom/theme/ocs/template/**`, `custom/theme/ocs/javascript/theme.js` (only sticky offsets)
- extension templates with layout classes: `ocs_home_slider`, `ocs_brand_strip`, `ocs_product_carousel`, `ocs_quick_view`, `ocs_viewed`
- `scripts/test/theme.test.mjs`, `custom/theme/ocs/DESIGN.md`

## Risks

- Layer order changes specificity everywhere: done section by section with screenshots.
- Subgrid support (Chrome 117+, Safari 16+, Firefox 71+): fallback path is tested by disabling the rule.
