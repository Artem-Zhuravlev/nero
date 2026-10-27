# TASKS — SPEC-069 Unified Alerts and No Click Outline

| Task         | Description                                                             | Fulfills                        | Check        | Status  |
| ------------ | ----------------------------------------------------------------------- | ------------------------------- | ------------ | ------- |
| TASK-ALR-001 | Unified `.alert` + close-button + fixed CSS (replace two old blocks)    | REQ-ALR-002, 003, 004, 005, 008 | TEST-ALR-001 | done    |
| TASK-ALR-002 | `OCS.alerts` normaliser: strip icons, close button, observer, auto-hide | REQ-ALR-001, 002, 005, 006, 010 | TEST-ALR-002 | done    |
| TASK-ALR-003 | Clean templates and theme JS alert markup, add `text_close`             | REQ-ALR-001, 007, 008, 010      | TEST-ALR-003 | done    |
| TASK-ALR-004 | Remove click outline, keep keyboard focus ring                          | REQ-ALR-009                     | TEST-ALR-004 | done    |
| TASK-ALR-005 | Verify, specs index, `npm run pack:theme`                               | REQ-ALR-011, 012                | TEST-ALR-005 | done    |

- **TEST-ALR-001** — verifies REQ-ALR-002, 003, 004, 005, 008: CSS check in browser at 320px and desktop, long text, cross inside the box.
- **TEST-ALR-002** — verifies REQ-ALR-001, 002, 005, 006, 010: wishlist/compare/cart alert from `common.js` is normalised; success fades, error stays.
- **TEST-ALR-003** — verifies REQ-ALR-001, 007, 008, 010: Node test greps theme templates for `fa fa-`, `&times;`, alert status icons.
- **TEST-ALR-004** — verifies REQ-ALR-009: click shows no outline, Tab shows ring.
- **TEST-ALR-005** — verifies REQ-ALR-011, 012: `npm run verify`, `npm test` green; archive rebuilt.
