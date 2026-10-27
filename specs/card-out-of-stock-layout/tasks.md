# TASKS — SPEC-077 Out-of-stock Cards

| Task         | Description                                      | Fulfills                          | Check        | Status  |
| ------------ | ------------------------------------------------ | --------------------------------- | ------------ | ------- |
| TASK-OOS-001 | View events expose `ocs_notify` on listing cards | REQ-OOS-001, 002, 003             | TEST-OOS-001 | done    |
| TASK-OOS-002 | `card.twig` bell / no button, `.is-out`          | REQ-OOS-001, 003, 004             | TEST-OOS-002 | done    |
| TASK-OOS-003 | Sprite `i-bell`                                  | REQ-OOS-001, 007                  | TEST-OOS-002 | done    |
| TASK-OOS-004 | CSS: grayscale, 18px price, even spacing         | REQ-OOS-004, 005, 006             | TEST-OOS-003 | done    |
| TASK-OOS-005 | Verify, README row, `npm run pack:theme`         | REQ-OOS-008                       | TEST-OOS-004 | done    |

- **TEST-OOS-001** — verifies REQ-OOS-001..003: php-lint of the events file; handler skips when module off.
- **TEST-OOS-002** — verifies REQ-OOS-001, 003, 007: card template contains bell button and `is-out`; sprite has `i-bell`.
- **TEST-OOS-003** — verifies REQ-OOS-004..006: stylesheet has the grayscale rule and no price size above 1.125rem in cards.
- **TEST-OOS-004** — verifies REQ-OOS-008: `npm run verify` green; archive rebuilt.
