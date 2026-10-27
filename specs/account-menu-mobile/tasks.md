# TASKS — SPEC-065 Account Menu Visible on Phones

| Task         | Description                                                | Fulfills                 | Check        | Status |
| ------------ | ---------------------------------------------------------- | ------------------------ | ------------ | ------ |
| TASK-AMM-001 | CSS: override `.hidden-xs` for the account menu column     | REQ-AMM-001, REQ-AMM-002 | TEST-AMM-001 | done   |
| TASK-AMM-002 | `npm run verify`, `npm run pack:theme`, README row, commit | all                      | TEST-AMM-002 | done   |

- **TEST-AMM-001** — verifies REQ-AMM-001, REQ-AMM-002: at 375px the strip shows on account pages; other side columns stay hidden.
- **TEST-AMM-002** — verifies all: `npm run verify` green, archive rebuilt.
