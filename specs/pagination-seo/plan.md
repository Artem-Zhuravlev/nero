# PLAN — SPEC-094

1. `ocs_listing/events.php`: при `page`>1 на category/manufacturer убрать `description`; новый обработчик `beforeHeader` дописывает суффикс к `title` и `description`.
2. Миграция `ocs_listing/db/003_header_event.php` регистрирует событие `catalog/view/common/header/before`; `extension.json` обновить.
3. Строка `ocs_page_suffix` в ru-ru/uk-ua/en-gb.
4. Тест в `scripts/test/theme.test.mjs`, `npm run verify`, `npm run pack:theme`, строка в `specs/README.md`.
