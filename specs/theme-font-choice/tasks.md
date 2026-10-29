# TASKS — SPEC-121 Выбор шрифта

| Task         | Description                                                                          | Fulfills                      | Check        | Status |
| ------------ | ------------------------------------------------------------------------------------ | ----------------------------- | ------------ | ------ |
| TASK-FCH-001 | `Ocs\Fonts`: каталог, `resolve`, наличие файлов, генерация `@font-face`/preload       | REQ-FCH-001, 002, 003, 004    | TEST-FCH-001, 002 | done |
| TASK-FCH-002 | Скачать и разложить woff2 для Inter, Manrope, Onest, Golos Text, Nunito Sans; README  | REQ-FCH-002, 005, 013         | TEST-FCH-003 | done |
| TASK-FCH-003 | Шапка: событие + `header.twig` (`<style>`, preload); убрать статические `@font-face`  | REQ-FCH-002, 003, 011         | TEST-FCH-002 | done |
| TASK-FCH-004 | Админ-форма темы: select, валидация, недоступные шрифты disabled, 3 языка             | REQ-FCH-001, 004              | TEST-FCH-001 | done |
| TASK-FCH-005 | Миграция дефолта `theme_ocs_font`                                                    | REQ-FCH-001, 010              | TEST-FCH-001 | done |
| TASK-FCH-006 | Подключить Gilroy / Proxima Nova / Geometria, когда будут лицензионные файлы          | REQ-FCH-004, 013              | TEST-FCH-003 | blocked |
| TASK-FCH-007 | Обновить тесты SPEC-102, DESIGN.md; проверка в браузере 375/1280 по каждому шрифту    | REQ-FCH-012                   | TEST-FCH-004 | partial (no browser check) |
| TASK-FCH-008 | `npm run verify`, `npm run pack:theme`, строка в `specs/README.md`, `implemented`     | all                           | all          | done |
