# TASKS — SPEC-005 Database and Migrations

| Task | Description | Fulfills | Check | Status |
| ---- | ----------- | -------- | ----- | ------ |
| TASK-DB-001 | Runner skeleton: arg parsing, DB bootstrap via core `DB`, lock, bookkeeping table | REQ-DB-001..003, 008, 070, 072 | TEST-DB-001 | pending |
| TASK-DB-002 | Ordering incl. extension dependencies | REQ-DB-004, 010 | TEST-DB-002 | pending |
| TASK-DB-003 | Failure handling, re-run, checksum abort, dry-run, backup check | REQ-DB-005..007, 009 | TEST-DB-003 | pending |
| TASK-DB-004 | `.sql` parser and tokens (`PREFIX`, `STORE_ID`, `LANG`, `GROUP`) | REQ-DB-011, 012 | TEST-DB-004 | pending |
| TASK-DB-005 | `.php` migration loader + Context | REQ-DB-013, 029 | TEST-DB-005 | pending |
| TASK-DB-006 | Helpers: setting, extension, permission | REQ-DB-020..022 | TEST-DB-006 | pending |
| TASK-DB-007 | Helpers: event, seoUrl, layout, module | REQ-DB-023..026 | TEST-DB-007 | pending |
| TASK-DB-008 | Helpers: ocmod, lookups, schema (own tables) | REQ-DB-027, 028, 030 | TEST-DB-008 | pending |
| TASK-DB-009 | Seeds runner | REQ-DB-014 | TEST-DB-009 | pending |
| TASK-DB-010 | `refresh.php` headless OCMOD + cache clear | REQ-DB-040, 041 | TEST-DB-010 output equals admin refresh | pending |
| TASK-DB-011 | Table/column conventions lint | REQ-DB-050..053 | TEST-DB-011 | pending |
| TASK-DB-012 | Verify checks + idempotence + schema snapshot | REQ-DB-060, 061 | TEST-DB-012 | pending |
| TASK-DB-013 | Perf check | REQ-DB-071 | review | pending |
