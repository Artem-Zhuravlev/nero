# PLAN — SPEC-104

1. Модель `ocs_menu/tree`: заменить `getBrands($ids,$limit)` на `getAllBrands()` (один кэшируемый запрос: `manufacturer` + `manufacturer_to_store`, порядок `sort_order, name`); убрать `CategoryTree::groupBrands`, если больше нигде не используется.
2. Контроллер `ocs_mega_menu`: при включённом `module_ocs_mega_menu_brands` собрать `data['brands']` со ссылками; из категорий убрать `brands`.
3. Шаблон: убрать `.ocs-mega-brands` из панели категории; после цикла категорий добавить `li.ocs-mega-item.ocs-mega-brandsitem` с флайаутом-сеткой брендов (`.ocs-mega-fly` + `.ocs-mega-brandlist`).
4. CSS/JS темы: стили списка брендов; убедиться, что hover/click/мобильная логика `theme.js` работает для нового `li` без баннера.
5. Админка: убрать поле лимита, подписи «Бренды в меню» во всех языках, валидацию лимита, ключ из `extension.json` (миграция `002_brands` оставить как есть — идемпотентна; значение лимита в БД безвредно).
6. Тесты, `npm run verify`, `npm run pack:theme`, строка SPEC-104 в `specs/README.md`.
