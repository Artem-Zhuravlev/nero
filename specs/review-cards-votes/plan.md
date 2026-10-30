# PLAN — SPEC-127 Карточки отзывов и «полезно / не полезно»

## Approach

1. Вёрстка: переписать `review.twig` на `<article class="ocs-rv">` и добавить стили в `stylesheet.css` (токены темы, Medium для имени, контурные иконки `i-thumb-up/down` в спрайт).
2. Данные: миграция `ocs_product_extras/db/005_review_votes.php` создаёт таблицу; событие `catalog/model/catalog/review/getReviewsByProductId/after` (как уже сделано для плюсов/минусов) добавляет `up/down/mine` одним запросом.
3. Эндпоинт `review_vote` в расширении + библиотека `Ocs\ReviewVote` (чистая логика, тестируется на SQLite).
4. `theme.js`: делегированный обработчик кликов, оптимистичное обновление, откат при ошибке.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Хранилище | отдельная таблица | не трогаем `review`; PK исключает дубли |
| Идентификатор гостя | cookie | IP ненадёжен за NAT/прокси |

## Affected files / directories

- `custom/theme/ocs/template/product/review.twig`, `custom/theme/ocs/stylesheet/stylesheet.css`, `custom/theme/ocs/javascript/theme.js`, `custom/theme/ocs/template/ocs/sprite.twig`
- `custom/extensions/ocs_product_extras/` (db, controller, library, языки)
- `custom/VERSION` (если миграции/расширения меняются), пересборка `dist/*.ocmod.zip`

## Risks

- Накрутка голосов -> cookie + лимит частоты; полной защиты нет (осознанно).
- Миграции не прогонялись на реальной MySQL -> тест на SQLite + ручная проверка.
