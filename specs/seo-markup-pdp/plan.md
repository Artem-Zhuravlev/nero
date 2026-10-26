# PLAN — SPEC-045 SEO Heading Markup and Product Page Info Block

## Approach

1. Inventory every `<h1>`-`<h6>` in theme and extension storefront templates and fix the list in the spec notes.
2. Fix levels per the decision: price `h2` -> `p.ocs-price`; module/section titles to the agreed level; review form title; `html` module title.
3. Rewrite the `col-sm-4` info column of `product.twig` with the new structure; CSS in the `components` layer using tokens; keep all core hooks.
4. Add `scripts/test/seo.test.mjs`: heading rules (REQ-SEO-001..004) over templates; product-title level over all card producers.
5. Rebuild the package, re-deploy to the test store, screenshot review.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Price element | `<p class="ocs-price">` + Offer microdata | headings are for the outline, not for emphasis |
| Spec list | `<dl>` | semantic key/value pairs, no list noise |
| Check | static template test | catches regressions without a running store |

## Affected files / directories

- `custom/theme/ocs/template/product/product.twig`, `ocs/card.twig`, `extension/module/*.twig`, `product/category.twig`, `blog/*.twig`
- `custom/extensions/*/upload/catalog/view/**/*.twig` with headings, `custom/theme/ocs/stylesheet/stylesheet.css`
- `scripts/test/seo.test.mjs`, `scripts/gen-theme-templates.mjs` (generated module templates change, so they leave the regenerate list)

## Risks

- Generated templates (`featured`, `latest`, ...) are regenerated from core -> take them out of the regenerate list (as done for compare in SPEC-044).
- A heading level change shifts CSS selectors keyed on `h3` -> grep the styles before editing.
