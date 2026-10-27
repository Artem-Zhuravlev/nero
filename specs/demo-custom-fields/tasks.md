# TASKS — SPEC-068

| Task         | Description                                                         | Fulfills                    | Check        | Status  |
| ------------ | ------------------------------------------------------------------- | --------------------------- | ------------ | ------- |
| TASK-DCF-001 | Extend catalogue.json: product, review, category, settings, records | REQ-DCF-002, 003, 005, 006, 021 | TEST-DCF-001 | done    |
| TASK-DCF-002 | Registry-aware setting writer (skip non-empty, clear on remove)     | REQ-DCF-004, 008            | TEST-DCF-002 | done    |
| TASK-DCF-003 | Product extras: video, questions, review pros/cons, stickers, discounts | REQ-DCF-001, 002, 003, 009 | TEST-DCF-003 | done    |
| TASK-DCF-004 | Theme/module settings and admin-list records                        | REQ-DCF-001, 004, 006, 009  | TEST-DCF-004 | done    |
| TASK-DCF-005 | Idempotence and removal                                             | REQ-DCF-007, 008            | TEST-DCF-005 | done    |
| TASK-DCF-006 | README-INSTALL, specs index, verify, pack:theme                     | REQ-DCF-020, 022            | TEST-DCF-006 | done    |

- **TEST-DCF-001** — verifies REQ-DCF-002, 003, 005, 021: data counts and sticker coverage.
- **TEST-DCF-002** — verifies REQ-DCF-004, 008: owner values untouched; demo values cleared.
- **TEST-DCF-003** — verifies REQ-DCF-001, 002, 009: rows in `ocs_product_video`, `ocs_question`, `ocs_review_extra`; missing tables skipped.
- **TEST-DCF-004** — verifies REQ-DCF-004, 006: settings and records present.
- **TEST-DCF-005** — verifies REQ-DCF-007, 008: second run adds no duplicates; remove restores counts.
- **TEST-DCF-006** — verifies REQ-DCF-020, 022: `npm run verify`, `npm run test:php` green; archive rebuilt.
