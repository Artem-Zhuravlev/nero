# PLAN — SPEC-048 Product Gallery Lightbox, YouTube Video, Store-wide Info Blocks

## Approach

1. **Lightbox** — `OCS.lightbox` in `theme.js` on top of `OCS.modal` (white dialog variant `ocs-lightbox`): builds the stage, arrows, counter, thumbnails and the summary from the gallery markup
   already on the page (`[data-ocs-gallery]`: full-size URLs are in the `a.thumbnail` hrefs, price/button from the side-card variables rendered into a `data-` block). `product.twig` removes the
   `magnificPopup` init for `.thumbnails`; the gallery links get `data-ocs-lightbox` and keep their `href` (no-JS fallback). Swipe via pointer events; images preloaded for index +/- 1.
2. **Video** — migration creates `oc_ocs_product_video`; `Ocs\Video` (pure) parses/validates ids and builds poster/embed URLs; admin: field in the existing OCS extras tab (`ocs_extras_tab_pane`),
   saved by the existing `addProduct/editProduct` after-events, removed by `deleteProduct`; catalog: `ocs_product_extras` events add `$x['video']` to the product data and the gallery
   (last slide), and a model method `videoFlags($ids)` used by the listing events (`ocs_listing`) to mark cards (`product.has_video`) in one query.
3. **Info blocks** — `Ocs\InfoBlocks` (sanitise, view) in the `ocs` extension; theme form editor (language tabs, row editor like SPEC-047); `product.twig` renders `ocs_info_blocks` under the buy form;
   the data comes from the theme view event (one settings read).
4. **Checks** — PHP unit tests for `Video` and `InfoBlocks`; template/JS/CSS tests; browser checks at 360/768/1280 px, network log shows no YouTube request before a click.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Lightbox | own module on `OCS.modal` | consistent look, focus handling and no extra library; restyling Magnific would not allow price/buy and the video slide |
| Video player | click-to-load nocookie iframe | privacy (no third-party request on load), fast page |
| Video storage | table keyed by product | survives product edits, one indexed query for list badges |
| Info blocks | global setting | requirement: set once, same on all products |

## Affected files / directories

- `custom/theme/ocs/javascript/theme.js`, `stylesheet/stylesheet.css`, `template/product/product.twig`, `ocs/card.twig`, module card templates, `ocs/sprite.twig`
- `custom/extensions/ocs_product_extras/**` (db migration, admin form/events/model, catalog events/model, language), `custom/extensions/ocs_listing/**` (video flag), `custom/extensions/ocs/**` (theme form, `InfoBlocks`)
- `custom/db/migrations/` or the extension `db/002_video.php`, `scripts/test/gallery.test.mjs`, `scripts/test/php/VideoInfoTest.php`

## Risks

- Magnific is bound by core on `.thumbnails` in other templates -> only the product page template is changed, other pages untouched.
- Large `theme.js` growth -> budget note in the spec; code kept ES5 and compact.
- Admin form field relies on the OCMOD tab placeholder -> the existing OCMOD already provides it; a refresh is required after install (documented).
