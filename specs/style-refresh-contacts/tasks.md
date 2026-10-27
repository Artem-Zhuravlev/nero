# TASKS — SPEC-067

| Task         | Description                                          | Fulfills                       | Check        | Status  |
| ------------ | ---------------------------------------------------- | ------------------------------ | ------------ | ------- |
| TASK-REF-001 | Accent, border, button height tokens                 | REQ-REF-001, 002, 003          | TEST-REF-001 | done    |
| TASK-REF-002 | Gilroy @font-face and `--font`                       | REQ-REF-004                    | TEST-REF-001 | done    |
| TASK-REF-003 | Placeholders in all theme forms                      | REQ-REF-005                    | TEST-REF-002 | done    |
| TASK-REF-004 | Contacts page: event, template, CSS                  | REQ-REF-006, 007               | TEST-REF-003 | done    |
| TASK-REF-005 | `npm run verify`, `npm run pack:theme`, README, commit | all                          | TEST-REF-004 | done    |

- **TEST-REF-001** — verifies REQ-REF-001..004: no `#0071e3`, button height 46/42, border token, font-face present.
- **TEST-REF-002** — verifies REQ-REF-005: every input/textarea in templates has a placeholder.
- **TEST-REF-003** — verifies REQ-REF-006, 007: contacts page renders settings data on desktop and 375px.
- **TEST-REF-004** — verifies all: `npm run verify` green, archive rebuilt.
