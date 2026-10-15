# TASKS — SPEC-019 Performance and Availability

| Task | Description | Fulfills | Check | Status |
| ---- | ----------- | -------- | ----- | ------ |
| TASK-PRF-001 | `budgets.json` + scenario format | REQ-PRF-001..004 | TEST-PRF-001 | pending |
| TASK-PRF-002 | `perf-bench.mjs` with comparison/regression exit | REQ-PRF-010 | TEST-PRF-002 | pending |
| TASK-PRF-003 | Query profiling aggregation | REQ-PRF-011 | TEST-PRF-003 | pending |
| TASK-PRF-004 | Lighthouse log process + perf dataset generator | REQ-PRF-012, 013 | review | pending |
| TASK-PRF-005 | Runtime profile doc (opcache, MySQL, compression) | REQ-PRF-020, 032 | review | pending |
| TASK-PRF-006 | `ocs_fpc` extension (key, bypass rules, headers) | REQ-PRF-022, 024, 071 | TEST-PRF-006 | pending |
| TASK-PRF-007 | FPC invalidation + fragments endpoint | REQ-PRF-023 | TEST-PRF-007 | pending |
| TASK-PRF-008 | HTTP caching headers rules (web config generation) | REQ-PRF-025 | TEST-PRF-008 | pending |
| TASK-PRF-009 | WebP/srcset image extension | REQ-PRF-026 | TEST-PRF-009 | pending |
| TASK-PRF-010 | Per-request lookup caching conventions | REQ-PRF-021 | review | pending |
| TASK-PRF-011 | Hot-write mitigations (disable trackers, batched views) | REQ-PRF-030, 031 | TEST-PRF-011 | pending |
| TASK-PRF-012 | Index check script + MyISAM→InnoDB runbook stub | REQ-PRF-033, 034 | TEST-PRF-012 | pending |
| TASK-PRF-013 | Frontend performance (critical CSS, JS policy, layout stability) | REQ-PRF-040..042 | manual audit | pending |
| TASK-PRF-014 | Availability/degradation (stale-while-error, 503 page) | REQ-PRF-050, 051 | TEST-PRF-014 | pending |
| TASK-PRF-015 | Capacity doc + SPOF list | REQ-PRF-052, 053 | review | pending |
| TASK-PRF-016 | Release gate and per-extension cost declaration | REQ-PRF-060, 061 | review | pending |
| TASK-PRF-017 | NFR checks | REQ-PRF-070, 072 | review | pending |
