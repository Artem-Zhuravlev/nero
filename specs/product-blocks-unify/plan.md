# PLAN — SPEC-099

1. `product.twig`: заменить related-блок на `<section class="ocs-modcards">` + `.ocs-cards` + `ocs/card.twig`.
2. Миграции событий `catalog/view/product/product/before` для обогащения `products` в `ocs_listing`, `ocs_stickers`, `ocs_ajax_cart` (по образцу SPEC-056, миграция 002).
3. `featured_article.twig` -> `blog_card.twig`; `blog/article.twig` -> `.ocs-cards`.
4. Убрать мёртвый CSS, TEST-PBU-001/002, `npm run verify`, `npm run pack:theme`, строка в `specs/README.md`.
