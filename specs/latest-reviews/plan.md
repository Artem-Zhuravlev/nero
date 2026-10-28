# PLAN — SPEC-081 Latest Reviews Module

## Approach

Обычный модуль-расширение по образцу `ocs_product_carousel` / `ocs_viewed`: admin-контроллер с формой
экземпляра (таблица `module`), catalog-контроллер `index($setting)`, модель с одним JOIN-запросом, twig-шаблон
со Swiper-каруселью темы. Регистрация и права — миграцией расширения `001_install.php`.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Хранение настроек | Таблица `module` (многоэкземплярный) | Стандарт OC для «куда угодно через макеты»; глобальные setting-ключи не дают несколько блоков |
| Запрос | Собственная модель с JOIN | Core `getReviewsByProductId` фильтрует по одному товару |
| Кэш | `$this->cache` 5 мин | Дёшево, блок на главной |
| Карусель | Swiper темы (SPEC-037) | Единый стиль, уже подключён |

## Affected files / directories

- `custom/extensions/ocs_latest_reviews/{extension.json,db/001_install.php}`
- `custom/extensions/ocs_latest_reviews/upload/admin/{controller,language,view}/...`
- `custom/extensions/ocs_latest_reviews/upload/catalog/{controller,model,language,view}/...`
- CSS темы (блок отзывов), demo catalogue (несколько демо-отзывов, если нет), `specs/README.md`, тесты в `scripts/test/`.
- `npm run pack:theme` → `dist/ocs-theme-<VERSION>.ocmod.zip`.

## Risks

- Full-page cache (SPEC-019) заморозит блок -> TTL 5 минут приемлем.
- Отзывы с HTML от пользователей -> экранирование на выводе (REQ-LRV-011).
