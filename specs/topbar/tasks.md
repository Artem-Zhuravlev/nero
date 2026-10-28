# TASKS — SPEC-082 Top Bar

| Task         | Description                                                                 | Fulfills                                   | Check        | Status |
| ------------ | --------------------------------------------------------------------------- | ------------------------------------------ | ------------ | ------ |
| TASK-TBR-001 | extension.json + миграция регистрации/прав/дефолтов                         | REQ-TBR-001, 010                           | TEST-TBR-001 | done |
| TASK-TBR-002 | `Ocs\Topbar::build`: текст, ссылка, кнопка, safeUrl, даты, ID, фон          | REQ-TBR-003, 004, 005, 007, 011, 012       | TEST-TBR-002 | done |
| TASK-TBR-003 | Событие header/before + правка `header.twig` (приоритет над анонсом)        | REQ-TBR-007, 008                           | TEST-TBR-003 | done |
| TASK-TBR-004 | CSS: режимы «текст» и «текст + кнопка», пресеты, мобильный вид, крестик     | REQ-TBR-005, 006, 008, 013                 | TEST-TBR-004 | done |
| TASK-TBR-005 | Admin: контроллер, форма с вкладками языков, валидация        | REQ-TBR-001, 002, 003, 004                 | TEST-TBR-005 | done |
| TASK-TBR-006 | Языки uk-ua / ru-ru / en-gb (admin + catalog)                               | REQ-TBR-013                                | TEST-TBR-006 | done |
| TASK-TBR-007 | Тесты (PHP: Topbar; Node: пакет/структура), скриншоты desktop/mobile        | all                                        | TEST-TBR-004 | done |
| TASK-TBR-008 | `npm run verify`, `npm run pack:theme`, строка в README, `implemented`      | all                                        | TEST-TBR-006 | done |
