# PLAN — SPEC-034 Category Listing

## Approach

Extend the category override from SPEC-023 and add overrides for the other listing templates that inline `.product-thumb`
(search, special, manufacturer_info, module blocks). Sort keys and card data are added by catalog events; load more fetches the
same URL with `page=N` and extracts the product container.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Load more | Fetch next page HTML | No new JSON API, same rendering path |
| Card partial | Include file shared by listing overrides | Replaces the missing `product/thumb` of core |

## Affected files / directories

- `custom/theme/ocs/template/product/{category,search,special,manufacturer_info}.twig`, `template/ocs/card.twig` (partial),
  `custom/extensions/ocs_listing/` for events.

## Risks

- Twig includes of a partial need the theme path resolved by core `event/theme` -> tested on a real instance.
