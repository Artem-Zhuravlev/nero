# TASKS — SPEC-084 Blog: Authors, Dates, Multilingual Settings, Comments, Likes

| Task         | Description                                                                 | Fulfills                                   | Check        | Status |
| ------------ | --------------------------------------------------------------------------- | ------------------------------------------ | ------------ | ------ |
| TASK-BLG-001 | extension.json, миграции (таблицы, права, события, `configblog_description`) | REQ-BLG-001, 021, 060                      | TEST-BLG-001 | done |
| TASK-BLG-002 | Admin: CRUD авторов (список, форма с вкладками языков, аватар, соцсети)      | REQ-BLG-001, 002, 006, 052                 | TEST-BLG-002 | done |
| TASK-BLG-003 | OCMOD формы статьи + события сохранения: автор, дата размещения              | REQ-BLG-003, 010                           | TEST-BLG-003 | done |
| TASK-BLG-004 | OCMOD + события мультиязычных настроек блога (админка и витрина)             | REQ-BLG-020, 021, 022                      | TEST-BLG-004 | done |
| TASK-BLG-005 | Витрина: блок автора, дата, JSON-LD `author`/`datePublished`                 | REQ-BLG-004, 005, 011                      | TEST-BLG-005 | done |
| TASK-BLG-006 | Голоса: контроллер/модель, UPSERT, лимит, счётчики                            | REQ-BLG-033, 040, 041, 061                 | TEST-BLG-006 | done |
| TASK-BLG-007 | Комментарии: попап со звёздами, список карточками, реакции, средний рейтинг   | REQ-BLG-030, 031, 032, 033, 034, 064                       | TEST-BLG-007 | done |
| TASK-BLG-008 | Общая карточка статьи + стиль модулей/страниц блога + «товары в статьях»      | REQ-BLG-050, 051, 062                      | TEST-BLG-008 | done |
| TASK-BLG-009 | Языки uk-ua / ru-ru / en-gb (admin + catalog)                                | REQ-BLG-063                                | TEST-BLG-009 | done |
| TASK-BLG-010 | Демо-данные: вынесено из SPEC-084 (демо-каталог SPEC-041 не содержит блога; статьи и комментарии берутся из примера ocStore)                                   | REQ-BLG-065                                | TEST-BLG-010 | done |
| TASK-BLG-011 | Тесты (PHP на SQLite + Node структура), `npm run verify`, `npm run pack:theme`, строка в README, статус `implemented` | all | TEST-BLG-011 | done |
