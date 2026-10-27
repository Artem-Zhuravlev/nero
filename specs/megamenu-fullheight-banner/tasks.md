# TASKS — SPEC-055 Mega Menu: Full-Height Flyout and Banner Column

| Task         | Description                                                    | Fulfills                          | Check        | Status |
| ------------ | -------------------------------------------------------------- | --------------------------------- | ------------ | ------ |
| TASK-MMF-001 | CSS: flyout anchored to the list, full height, scrolls         | REQ-MMF-001, 002, 005, 006        | TEST-MMF-001 | done   |
| TASK-MMF-002 | Twig: `.ocs-mega-body` + banner column; controller image size  | REQ-MMF-003, 004, 007             | TEST-MMF-002 | done   |
| TASK-MMF-003 | `npm run verify`, `npm run pack:theme`, README row             | all                               | TEST-MMF-003 | done   |

- TEST-MMF-001 — verifies REQ-MMF-001, REQ-MMF-005: node test checks the stylesheet rule (`top: 0; bottom: 0`, no `margin-left`) for the open flyout.
- TEST-MMF-002 — verifies REQ-MMF-003, REQ-MMF-004, REQ-MMF-007: node test checks twig has `.ocs-mega-body` and `.ocs-mega-promo` as siblings and the controller resizes 480x720.
- TEST-MMF-003 — verifies all: verify green, archive rebuilt.
