# TASKS — SPEC-054 Breadcrumbs: schema.org Markup, No Self-Link, "Home" as a Word

| Task         | Description                                                        | Fulfills                    | Check        | Status  |
| ------------ | ------------------------------------------------------------------ | --------------------------- | ------------ | ------- |
| TASK-BRC-001 | New breadcrumb block in all storefront templates                   | REQ-BRC-001, 002, 004       | TEST-BRC-001 | done |
| TASK-BRC-002 | `ocs_breadcrumb_home` strings + event that replaces the `fa-home` text | REQ-BRC-003             | TEST-BRC-002 | done |
| TASK-BRC-003 | CSS for the current item                                           | REQ-BRC-005                 | TEST-BRC-003 | done |
| TASK-BRC-004 | `npm run verify`, `npm run pack:theme`, README row                 | all                         | TEST-BRC-004 | done |

- TEST-BRC-001 — verifies REQ-BRC-001, REQ-BRC-002, REQ-BRC-004: node test greps all templates for one identical block and no `#i-home` in breadcrumbs.
- TEST-BRC-002 — verifies REQ-BRC-003: key exists in all three languages; event replaces `fa-home`.
- TEST-BRC-003 — verifies REQ-BRC-005: stylesheet has the `.breadcrumb [aria-current]` rule.
- TEST-BRC-004 — verifies all: verify green, archive rebuilt.
