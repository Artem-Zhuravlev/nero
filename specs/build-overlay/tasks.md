# TASKS — SPEC-003 Build Overlay

| Task | Description | Fulfills | Check | Status |
| ---- | ----------- | -------- | ----- | ------ |
| TASK-BLD-001 | Layer collector with excludes, sorted POSIX paths, symlink rejection | REQ-BLD-001, 002, 003 | TEST-BLD-001 | pending |
| TASK-BLD-002 | Collision detector (case-insensitive) + exit code 2 | REQ-BLD-010, 011 | TEST-BLD-002 | pending |
| TASK-BLD-003 | Admin dir rewrite + validation | REQ-BLD-020, 021 | TEST-BLD-003 | pending |
| TASK-BLD-004 | Config templates + renderer + escaping | REQ-BLD-030, 031, 035 | TEST-BLD-004 constants match installer list | pending |
| TASK-BLD-005 | `.htaccess` / robots generation | REQ-BLD-032, 033 | TEST-BLD-005 | pending |
| TASK-BLD-006 | `BUILD_INFO.json` + treeHash | REQ-BLD-034, 052 | TEST-BLD-006 | pending |
| TASK-BLD-007 | Storage skeleton sync incl. vendor | REQ-BLD-040, 041, 042 | TEST-BLD-007 | pending |
| TASK-BLD-008 | Incremental copy, stale cleanup, runtime-dir protection, atomic generated writes | REQ-BLD-050, 051, 053 | TEST-BLD-008 | pending |
| TASK-BLD-009 | CLI flags, exit codes, stable output | REQ-BLD-060, 061, 062 | TEST-BLD-009 | pending |
| TASK-BLD-010 | NFR checks (no network, built-ins only) | REQ-BLD-070, 071, 072 | review | pending |
