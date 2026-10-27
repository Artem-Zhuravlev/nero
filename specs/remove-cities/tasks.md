# TASKS — SPEC-052 Remove the Cities Feature

| Task         | Description                                                    | Fulfills             | Check        | Status  |
| ------------ | -------------------------------------------------------------- | -------------------- | ------------ | ------- |
| TASK-RCT-001 | Migration 0008: unregister, drop tables                        | REQ-RCT-003, 004     | TEST-RCT-001 | done |
| TASK-RCT-002 | Delete extension, header/product hooks, theme.js, CSS, demo    | REQ-RCT-001, 002, 005 | TEST-RCT-002 | done |
| TASK-RCT-003 | Remove tests of the feature; add no-leftovers test             | REQ-RCT-010          | TEST-RCT-003 | done |
| TASK-RCT-004 | verify, test, pack:theme, README row, SPEC-032 superseded      | REQ-RCT-010, 011     | TEST-RCT-004 | done |

- **TEST-RCT-001** — verifies REQ-RCT-003, REQ-RCT-004: migration is idempotent (PHP runner).
- **TEST-RCT-002** — verifies REQ-RCT-001, REQ-RCT-002, REQ-RCT-005: header/product/theme.js/CSS contain no city hooks.
- **TEST-RCT-003** — verifies REQ-RCT-010: no `ocs_city` outside the migration.
- **TEST-RCT-004** — verifies REQ-RCT-011: `npm run verify` green, archive rebuilt.
