# TASKS — SPEC-120 Out-of-stock bell and out-of-stock last

| Task          | Description                                                        | Fulfills                       | Check          | Status  |
| ------------- | ------------------------------------------------------------------ | ------------------------------ | -------------- | ------- |
| TASK-OOS2-001 | Migration enabling `ocs_back_in_stock`;     | REQ-OOS2-003                   | TEST-OOS2-001  | done |
| TASK-OOS2-002 | Pin the bell wiring in a test (no template change)      | REQ-OOS2-001, REQ-OOS2-002     | TEST-OOS2-002  | done |
| TASK-OOS2-003 | OCMOD `ocs_listing_stock_last` for getProducts/getProductSpecials  | REQ-OOS2-004, 005, 006         | TEST-OOS2-003  | done |
| TASK-OOS2-004 | Verify, `npm run pack:theme`, README row             | REQ-OOS2-007                   | TEST-OOS2-004  | done |

- TEST-OOS2-001 — verifies REQ-OOS2-003: migration is idempotent and sets the status to 1.
- TEST-OOS2-002 — verifies REQ-OOS2-001, REQ-OOS2-002: card template contains the bell wired to the notify form, hidden only when the module is off.
- TEST-OOS2-003 — verifies REQ-OOS2-004, REQ-OOS2-005, REQ-OOS2-006: OCMOD search strings match core exactly as expected.
- TEST-OOS2-004 — verifies REQ-OOS2-007: `npm run verify` and `npm test` green.
