# TASKS — SPEC-063 Customer Account Area in the Site Style

| Task         | Description                                      | Fulfills                                   | Check        | Status  |
| ------------ | ------------------------------------------------ | ------------------------------------------ | ------------ | ------- |
| TASK-ACC-001 | CSS: account grid, tiles, sidebar, tables, badges | REQ-ACC-002, 005, 006, 007, 010, 011, 014 | TEST-ACC-001 | done    |
| TASK-ACC-002 | Dashboard template with icon cards               | REQ-ACC-001, 003                           | TEST-ACC-001 | done    |
| TASK-ACC-003 | Sidebar template with icons and active state     | REQ-ACC-004, 005                           | TEST-ACC-002 | done    |
| TASK-ACC-004 | List templates (orders, downloads, rewards, …)   | REQ-ACC-007, 008, 009, 010                 | TEST-ACC-003 | done    |
| TASK-ACC-005 | Forms and login/register spacing                 | REQ-ACC-011, 012                           | TEST-ACC-003 | done    |
| TASK-ACC-006 | `npm run verify`, `npm run pack:theme`, README row | REQ-ACC-013                              | TEST-ACC-004 | done    |

- **TEST-ACC-001** — verifies REQ-ACC-001..003: dashboard renders cards at 375/1280 px, all links resolve.
- **TEST-ACC-002** — verifies REQ-ACC-004, 005: active item on each account route; phone layout.
- **TEST-ACC-003** — verifies REQ-ACC-006..012: order list without horizontal scroll at 375 px; empty states; forms.
- **TEST-ACC-004** — verifies REQ-ACC-013, 014: `npm run verify` green, archive rebuilt, no core diff.
