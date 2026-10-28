# TASKS — SPEC-057 Compare Page

| Task         | Description | Fulfills    | Check        | Status  |
| ------------ | ----------- | ----------- | ------------ | ------- |
| TASK-CMP-001 | `Bookmarks::groups/trim` pure functions, per-category limits | REQ-CMP-001, 002, 006 | TEST-CMP-001 | done |
| TASK-CMP-002 | Model: product→deepest category, category names (batched) | REQ-CMP-001, 010 | TEST-CMP-002 | done |
| TASK-CMP-003 | Controller/view events, `same` flags, safe fallback; migration 002 | REQ-CMP-002, 003, 007, 011 | TEST-CMP-003 | done |
| TASK-CMP-004 | Trimming in the hydrate event (no toast, no sync change) | REQ-CMP-006 | TEST-CMP-003 | done |
| TASK-CMP-005 | `compare.twig` rewrite (tabs, switch, sticky table, collapsible groups, buy/remove) | REQ-CMP-002..005, 013 | TEST-CMP-005 | done |
| TASK-CMP-006 | CSS + JS (switch, persist, remove, collapse, 320px) | REQ-CMP-003..005 | TEST-CMP-005 | done |
| TASK-CMP-007 | Balance-scale icon in sprite | REQ-CMP-008 | TEST-CMP-005 | done |
| TASK-CMP-008 | Language strings uk-ua / ru-ru / en-gb | REQ-CMP-012 | TEST-BKM-001 | done |
| TASK-CMP-009 | README row, `npm run verify`, `npm run pack:theme` | all | npm run verify | done |
| TASK-CMP-010 | Cap the compare table wrapper width by column count | REQ-CMP-014 | TEST-CMP-006 | done |
