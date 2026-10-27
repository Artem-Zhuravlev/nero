# TASKS — SPEC-075 Brands Page Visual Redesign

| Task         | Description                                   | Fulfills                          | Check        | Status  |
| ------------ | --------------------------------------------- | --------------------------------- | ------------ | ------- |
| TASK-MFP-001 | Template markup: chip index, grid, tiles       | REQ-MFP-001, REQ-MFP-002          | TEST-MFP-001 | done |
| TASK-MFP-002 | CSS (tokens, layer, responsive, `[hidden]`)    | REQ-MFP-002, 003, 004, 005        | TEST-MFP-002 | done |
| TASK-MFP-003 | Verify, specs index, `npm run pack:theme`      | REQ-MFP-005                       | TEST-MFP-003 | done |

- **TEST-MFP-001** — verifies REQ-MFP-001, REQ-MFP-002: chips and tiles render on the brands page.
- **TEST-MFP-002** — verifies REQ-MFP-002, REQ-MFP-003, REQ-MFP-004: layout at 1280/768/375 px, anchor offset, no horizontal scroll, search still filters.
- **TEST-MFP-003** — verifies REQ-MFP-005: `npm run verify` green; archive rebuilt.
