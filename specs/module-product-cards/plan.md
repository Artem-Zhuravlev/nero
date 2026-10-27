# PLAN — SPEC-056 Default Product Modules in the Site Card Style

## Approach

Replace the card markup in the five module overrides with the shared partial and feed it the same keys the listing
pages get. Enrichment reuses the existing handlers: each of `ocs_listing`, `ocs_stickers`, `ocs_ajax_cart` gets a
`beforeModule` method that delegates to its current private `apply/listing/labels` code, plus a numbered migration that
registers the five module triggers. The grid is plain CSS (a wrapper class on the module) using the container-query
approach of SPEC-038; no JS.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Layout | CSS grid, not swiper carousel | Works in side columns; the home page already has carousels (`ocs_product_carousel`) |
| Data | Per-extension events on module routes | Same pattern as listing pages; no cross-extension calls |
| Card | Shared `ocs/card.twig` | One markup for all product cards; no copy to maintain |

## Affected files / directories

- `custom/theme/ocs/template/extension/module/{featured,special,latest,bestseller,featured_product}.twig`
- `custom/theme/ocs/stylesheet/stylesheet.css`
- `custom/extensions/ocs_listing|ocs_stickers|ocs_ajax_cart/` (controller events, `db/002_module_events.php`, `extension.json`)
- `specs/README.md`, `custom/VERSION`, `dist/ocs-theme-<VERSION>.ocmod.zip`

## Risks

- `.product-layout.col-*` styles leaking into the module grid -> wrapper resets them as `ocs_product_carousel` does.
- Event ordering vs. ocs_listing quantity (needed by ocs_ajax_cart) -> keep the same sort orders as the listing events.
