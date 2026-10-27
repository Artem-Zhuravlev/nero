# PLAN — SPEC-064

1. `ocs_bookmarks/.../controller/.../wishlist.php`: resize with `image_product_*`; add `description` (stripped, 100 chars like category), `rating`, `reviews`, `tax`, `discount_pct`.
2. `theme/ocs/template/account/wishlist.twig`: replace the table with `<div class="ocs-cards">` + `{% include 'ocs/template/ocs/card.twig' %}`.
3. `theme/ocs/template/ocs/card.twig`: `{% if product.remove %}` show the remove link instead of the heart (no effect on other pages: they have no `product.remove`).
4. `stylesheet.css`: `.ocs-tool-remove` always visible.
5. `npm run verify`, `npm run pack:theme`, README row.
