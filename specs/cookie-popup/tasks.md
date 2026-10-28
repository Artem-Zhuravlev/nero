# TASKS — SPEC-083 Cookie Popup

| Task         | Description                                                                  | Fulfills                                 | Check        | Status |
| ------------ | ---------------------------------------------------------------------------- | ---------------------------------------- | ------------ | ------ |
| TASK-CKP-001 | extension.json + миграция регистрации/прав/дефолтов (тексты на 3 языках)     | REQ-CKP-001, 002, 010                    | TEST-CKP-001 | done |
| TASK-CKP-002 | `Ocs\Cookie::build`: тексты по языку, safeUrl, версия, срок                  | REQ-CKP-002, 003, 011, 012               | TEST-CKP-002 | done |
| TASK-CKP-003 | Событие footer/before + блок в `footer.twig`                                 | REQ-CKP-003, 013, 014                    | TEST-CKP-003 | done |
| TASK-CKP-004 | JS: показ по cookie, запись `ocs_consent`, `window.ocsConsent`, `ocs:consent`, reopen | REQ-CKP-004, 005, 006, 007      | TEST-CKP-004 | done |
| TASK-CKP-005 | CSS: карточка/полоса, мобильный вид над tab bar, фокус, без CLS              | REQ-CKP-002, 008, 013                    | TEST-CKP-005 | done |
| TASK-CKP-006 | Admin: контроллер, форма с вкладками языков, валидация                       | REQ-CKP-001, 002, 003                    | TEST-CKP-006 | done |
| TASK-CKP-007 | Языки uk-ua / ru-ru / en-gb (admin + catalog)                                | REQ-CKP-013                              | TEST-CKP-007 | done |
| TASK-CKP-008 | Тесты (PHP: Cookie; Node: пакет/структура), скриншоты desktop/mobile         | all                                      | TEST-CKP-005 | done |
| TASK-CKP-009 | `npm run verify`, `npm run pack:theme`, строка в README, `implemented`       | all                                      | TEST-CKP-007 | done |
