# TASKS — SPEC-040 Installable Theme Package

| Task          | Description                                                            | Fulfills                                  | Check                         | Status      |
| ------------- | ---------------------------------------------------------------------- | ----------------------------------------- | ----------------------------- | ----------- |
| TASK-PKG-001  | Packer: merge theme + extensions into `upload/`, allow-list, zip       | REQ-PKG-001, 002, 003, 023                | TEST-PKG-001, TEST-PKG-002, TEST-PKG-010 | done |
| TASK-PKG-002  | OCMODs carried in payload, installed by migrations                     | REQ-PKG-004                               | TEST-PKG-001                  | done        |
| TASK-PKG-003  | Bundle migration runner + migrations into `system/library/ocs/setup/`  | REQ-PKG-005, 021                          | TEST-PKG-003, TEST-PKG-006    | done        |
| TASK-PKG-004  | Admin `extension/ocs/setup` (pre-check, install, activate, uninstall)  | REQ-PKG-005..008, 010, 022                | TEST-PKG-004, TEST-PKG-005, TEST-PKG-007, TEST-PKG-008 | done |
| TASK-PKG-005  | Opt-in demo data action (shared DemoSeed)                              | REQ-PKG-009                               | TEST-PKG-011, TEST-PKG-012                 | done |
| TASK-PKG-006  | README-INSTALL.md / docs/theme-install.md                              | REQ-PKG-011                               | TEST-PKG-001                  | done        |
| TASK-PKG-007  | Secrets/PII scan of the package                                        | REQ-PKG-020                               | TEST-PKG-009                  | done        |
| TASK-PKG-008  | End-to-end on a stock ocStore (install via zip, setup, 3 pages, rerun) | acceptance criteria                       | TEST-PKG-012                  | done        |
