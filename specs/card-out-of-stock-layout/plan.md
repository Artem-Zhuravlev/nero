# PLAN — SPEC-077

1. `ocs_back_in_stock`: add events `catalog/view/product/{category,search,manufacturer_info,special}/before` and `extension/module/{featured,latest,bestseller,special}/before` (+ viewed/wishlist routes that already feed `ocs/card.twig`) that set `products[i].ocs_notify` for products with `quantity <= 0` when the module is on. Reuse `beforeProduct` string/URL logic; register in `extension.json` + install migration.
2. `card.twig`: add `is-out` class when `ocs_stock.state == 'out'`; swap cart button for the bell button (`ocs_notify`) or nothing.
3. Sprite: add `i-bell` symbol.
4. `stylesheet.css`: `.product-thumb.is-out .image img { filter: grayscale(.8) }`; price `1.125rem`, remove clamp/nowrap rules; unified spacing tokens in the card block.
5. Verify (`npm run verify`, `npm test`), `npm run pack:theme`, add row to `specs/README.md`.
