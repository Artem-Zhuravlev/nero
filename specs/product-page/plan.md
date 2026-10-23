# PLAN — SPEC-035 Product Page

## Approach

Extend the `product/product` override: section bar and key attributes in markup, carousels reuse `OCS.carousel` (SPEC-033).
Extras (Q&A, accessories) live in `ocs_product_extras`; data reaches the template through a view event on `product/product`.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Q&A storage | Own table | Core review table has no answer threading |
| Accessories | Own link table | Core "related" has one list only |

## Affected files / directories

- `custom/theme/ocs/template/product/product.twig`, `custom/extensions/ocs_product_extras/`, migrations.

## Risks

- Core product JS depends on DOM ids -> hooks list in `HOOKS.md`, covered by an automated hook test.
