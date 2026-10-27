# TASKS — SPEC-056 Default Product Modules in the Site Card Style

| Task         | Description                                                      | Fulfills                              | Check        | Status  |
| ------------ | ---------------------------------------------------------------- | ------------------------------------- | ------------ | ------- |
| TASK-MPC-001 | Rewrite five module templates to include `ocs/card.twig`         | REQ-MPC-001, REQ-MPC-005              | TEST-MPC-001 | done |
| TASK-MPC-002 | `beforeModule` handlers + 002 migrations in the three extensions | REQ-MPC-002, REQ-MPC-005, REQ-MPC-010 | TEST-MPC-002 | done |
| TASK-MPC-003 | Module grid CSS (content area, side columns, 320px)              | REQ-MPC-003, REQ-MPC-004, REQ-MPC-011 | TEST-MPC-003 | done |
| TASK-MPC-004 | `npm run verify`, `npm run pack:theme`, README row, status       | REQ-MPC-001, REQ-MPC-002, REQ-MPC-003, REQ-MPC-004, REQ-MPC-005 | TEST-MPC-004 | done |

- **TEST-MPC-001** — verifies REQ-MPC-001, REQ-MPC-005: templates contain the include and no `.button-group`; Twig renders with empty and partial data.
- **TEST-MPC-002** — verifies REQ-MPC-002, REQ-MPC-010: migration registers five triggers per extension; handlers tolerate missing keys.
- **TEST-MPC-003** — verifies REQ-MPC-003, REQ-MPC-004: manual check in content area, left column and 320px.
- **TEST-MPC-004** — verifies REQ-MPC-001, REQ-MPC-002, REQ-MPC-003, REQ-MPC-004, REQ-MPC-005: `npm run verify` green, archive rebuilt.
