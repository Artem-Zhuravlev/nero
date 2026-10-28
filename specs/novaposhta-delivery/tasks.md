# TASKS — SPEC-085 Доставка «Нова пошта»

| Task         | Description | Fulfills | Check | Status  |
| ------------ | ----------- | -------- | ----- | ------- |
| TASK-NP-001  | Каркас расширения `ocs_novaposhta` (extension.json, shipping-контракт, права, языки) | REQ-NP-030, 053 | TEST-NP-001 | done |
| TASK-NP-002  | Миграция: таблицы `ocs_np_*`, настройки по умолчанию, события | REQ-NP-001, 003, 053, 055 | TEST-NP-002 | done |
| TASK-NP-003  | `Client` API НП v2.0 (таймауты, повторы, маскирование ключа) и транспорт-заглушка в тестах | REQ-NP-008, 052 | TEST-NP-003 | done |
| TASK-NP-004  | `Sync`: страницы, upsert по хешу, курсор, `GET_LOCK`, деактивация после полного прогона, `--dry-run`, классификация точек | REQ-NP-001..004, 007, 020 | TEST-NP-004 | done |
| TASK-NP-005  | CLI-вход `bin/sync.php` и HTTP-вход `cron` (токен, `hash_equals`, порция ≤ 50 с) | REQ-NP-005, 055 | TEST-NP-005 | done |
| TASK-NP-006  | `Repository` + JSON-эндпоинты `config`, `cities`, `warehouses` (индексы, throttle, лимиты) | REQ-NP-010, 020, 050, 051 | TEST-NP-006 | done |
| TASK-NP-007  | `getQuote`, эндпоинт `select`, события `addOrder`/`editOrder`, таблица `ocs_np_order` | REQ-NP-021, 030..032 | TEST-NP-007 | done |
| TASK-NP-008  | Админка: настройки, статус, ручной запуск порции и сброс курсора, блок Cron | REQ-NP-006, 030, 042, 052 | TEST-NP-008 | done |
| TASK-NP-009  | JS-компонент: автокомплит города, окно отделений с поиском и фильтром типа, клавиатура/ARIA, сброс выбора, регион и страна | REQ-NP-010..013, 020..023 | TEST-NP-009 | done |
| TASK-NP-010  | Пресеты `default`/`simple`/`custom`, переинициализация, подключение только на страницах чекаута | REQ-NP-040..042 | TEST-NP-010 | done |
| TASK-NP-011  | Локализация uk-ua / ru-ru / en-gb и стили | REQ-NP-054 | TEST-NP-011 | done |
| TASK-NP-012  | Сопоставление области НП с зоной OpenCart | REQ-NP-012 | TEST-NP-012 | done |
| TASK-NP-013  | `npm run verify`, `npm test`, `npm run pack:theme`; ручная проверка с боевым ключом и Simple — на владельце | REQ-NP-050 | TEST-NP-013 | in-progress |
