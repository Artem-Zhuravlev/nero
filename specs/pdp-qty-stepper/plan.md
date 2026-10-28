# PLAN — SPEC-101

1. В событии `ocs` для `product/product` вычислить `ocs_qty` = `{min, max}` (max = 0, если остаток не ограничивает).
2. В `product.twig` обернуть поле в `.ocs-qty` с кнопками, `data-min`/`data-max`.
3. В `theme.js` — нормализация количества и обработчики; в `stylesheet.css` — компактный стиль (пилюля, как кнопки сайта).
4. Тест, `npm run verify`, `npm run pack:theme`, строка в `specs/README.md`.
