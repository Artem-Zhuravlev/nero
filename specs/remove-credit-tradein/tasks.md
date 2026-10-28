# TASKS — SPEC-087 Remove Instalments and Trade-in

| Task         | Description                                                            | Fulfills                    | Check        | Status  |
| ------------ | ---------------------------------------------------------------------- | --------------------------- | ------------ | ------- |
| TASK-RCI-001 | Migration 0012: unregister, drop `ocs_tradein_lead`                    | REQ-RCI-002, 003            | TEST-RCI-001 | done |
| TASK-RCI-002 | Delete extensions, twig hooks, theme.js, CSS, DESIGN.md                | REQ-RCI-001, 002, 004       | TEST-RCI-002 | done |
| TASK-RCI-003 | Demo seeds (settings, leads) and tests of the modules removed          | REQ-RCI-005, 010            | TEST-RCI-003 | done |
| TASK-RCI-004 | verify, test, test:php, pack:theme, README row, SPEC-036 superseded    | REQ-RCI-010, 011            | TEST-RCI-004 | done |

- **TEST-RCI-001** — verifies REQ-RCI-002, REQ-RCI-003: migration is idempotent (PHP runner).
- **TEST-RCI-002** — verifies REQ-RCI-001, REQ-RCI-004: product/category twig, theme.js, CSS contain no credit/trade-in hooks.
- **TEST-RCI-003** — verifies REQ-RCI-005, REQ-RCI-010: no `ocs_credit`/`ocs_tradein` outside the migration.
- **TEST-RCI-004** — verifies REQ-RCI-011: `npm run verify` green, archive rebuilt.
