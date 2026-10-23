# PLAN — SPEC-037 Swiper and Brief Gaps

## Approach

Keep the markup contract of `OCS.carousel`; replace its engine with `new Swiper()` (core 3.4.2, jQuery build already loaded in the
header). The product gallery becomes two linked Swipers. Quick view is a server-rendered body (same option markup as the product
page, extracted into a partial) opened in `OCS.modal`. Option-level stock is computed by one library function used by the page data,
the quick view and the request endpoints.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Swiper version | Core 3.4.2 | Already shipped and loaded; no download; API is stable |
| Quick view body | Server HTML | Same validation and options as the product page |
| Restock trigger | Cron endpoint + admin event | Stock changes outside the admin form have no common event |
| Option stock rule | `Ocs\Stock::optionOut()` pure function | Testable, shared by page data and endpoints |

## Affected files / directories

- `custom/theme/ocs/javascript/theme.js`, `stylesheet.css`, `template/product/product.twig`, `template/ocs/card.twig`
- `custom/extensions/ocs_home_slider`, `ocs_brand_strip`, `ocs_product_carousel`, `ocs_product_extras` (templates)
- new `custom/extensions/ocs_quick_view/`; changes in `ocs_preorder`, `ocs_back_in_stock`, `ocs_stickers`
- migration 002 of back-in-stock

## Risks

- Swiper 3 API differs from newer docs: tests cover the option names used.
- theme.js budget: the custom carousel code is deleted first.
