# TASKS — SPEC-088 Logo in the Top Bar

| Task         | Description                                                              | Fulfills                                   | Check        | Status |
| ------------ | ------------------------------------------------------------------------ | ------------------------------------------ | ------------ | ------ |
| TASK-HLT-001 | grep использования `#logo`/`.ocs-head-logo` в CSS/JS/тестах              | REQ-HLT-001, 002                           | TEST-HLT-001 | done |
| TASK-HLT-002 | `header.twig`: логотип в `#top`, мобильный экземпляр, контакты → язык    | REQ-HLT-001, 002, 003, 004, 010, 011       | TEST-HLT-001 | done |
| TASK-HLT-003 | CSS: раскладка `#top`, отступ, сетка шапки, меню контактов вправо        | REQ-HLT-003, 005, 006, 007, 012            | TEST-HLT-002 | done |
| TASK-HLT-004 | Тест темы: порядок элементов, телефон в кнопке, fallback                 | REQ-HLT-001, 003, 004                      | TEST-HLT-001 | done |
| TASK-HLT-005 | Скриншоты desktop/tablet/mobile, `npm run verify`, `npm run pack:theme`  | all                                        | TEST-HLT-002 | done |
