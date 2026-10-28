# PLAN — SPEC-106

1. Шаблоны `featured/latest/special/bestseller/featured_product` и `ocs_viewed`: общий партиал-обёртка карусели (как в `ocs_product_carousel.twig`), заголовок `h2.ocs-sec-title` сохраняется.
2. CSS: `.ocs-modcards .ocs-carousel-item`, `.ocs-viewed .ocs-carousel-item`, `.ocs-pcarousel .ocs-carousel-item` -> `flex: 0 0 calc((100% - var(--sp-2)) / 2)` на `<768px`; на `>=768px` track модулей — grid (как `.ocs-cards`), стрелки скрыты.
3. `theme.js`: `OCS.carousel.init` для карусели с `data-mobile-only` создаётся на `<768px`, на десктопе уничтожается (`swiper.destroy(true, true)`), слушатель `matchMedia('change')`.
4. `product.twig`: блоки товаров -> те же классы/ширина.
5. Тест (Node, статический + проверка CSS-правил), `npm run verify`, `npm run pack:theme`, строка в `specs/README.md`.
