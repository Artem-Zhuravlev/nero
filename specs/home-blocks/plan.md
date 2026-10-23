# PLAN — SPEC-033 Home Blocks

## Approach

Each block is a multi-instance `module` extension (SPEC-004 REQ-EXT-022) rendered in layout positions; carousel JS is a small
shared script in the theme (`OCS.carousel`) using scroll-snap with buttons, no library.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Carousel | CSS scroll-snap + ~1 KB JS | Swiper/Owl would blow the JS budget |
| Newsletter | Own list + hook | Core newsletter only covers registered customers |

## Affected files / directories

- `custom/extensions/ocs_home_slider|ocs_brand_strip|ocs_product_carousel|ocs_newsletter/`, theme `javascript/theme.js`.

## Risks

- Several blocks add queries -> per-block cache (SPEC-019).
