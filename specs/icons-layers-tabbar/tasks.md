# TASKS — SPEC-042 SVG-Only Icons, Stars, Layer Scale, Bottom Bar

| Task          | Description                                                        | Fulfills                          | Check         | Status  |
| ------------- | ------------------------------------------------------------------ | --------------------------------- | ------------- | ------- |
| TASK-ICN-001  | Extend the sprite with all missing symbols; `OCS.icon()` helper    | REQ-ICN-002, 021                  | TEST-ICN-002  | done    |
| TASK-ICN-002  | Replace `fa` markup in theme templates and module templates        | REQ-ICN-001, 020                  | TEST-ICN-001  | done    |
| TASK-ICN-003  | `theme.js` strings and `CategoryTree::icon()` mapping              | REQ-ICN-001, 003                  | TEST-ICN-003  | done    |
| TASK-ICN-004  | Static tests: no `fa` classes, every `#i-x` exists                 | REQ-ICN-004                       | TEST-ICN-001  | done    |
| TASK-STR-001  | `.ocs-rating` component in cards, product page, reviews            | REQ-STR-001, 002, 003             | TEST-STR-001  | done    |
| TASK-Z-001    | Token scale and replacement of every z-index literal               | REQ-Z-001, 002                    | TEST-Z-001    | done    |
| TASK-Z-002    | Fix trapped drop-downs; overlap check script on the test store     | REQ-Z-003, 004                    | TEST-Z-002    | done    |
| TASK-TAB-001  | Bottom bar: no Home, header icons, under modals                    | REQ-TAB-001, 002, 003             | TEST-TAB-001  | done    |
| TASK-ICN-005  | Drop the FA stylesheet import if nothing needs it                  | REQ-ICN-005                       | TEST-ICN-001  | done    |
| TASK-ICN-006  | Rebuild package, verify on the test store, screenshots             | acceptance criteria               | TEST-ICN-004  | done    |
