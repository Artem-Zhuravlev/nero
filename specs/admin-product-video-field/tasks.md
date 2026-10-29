# TASKS — SPEC-115 Admin product form: video field on Data tab

| Task         | Description | Fulfills | Check | Status |
| ------------ | ----------- | -------- | ----- | ------ |
| TASK-PRD-120 | OCMOD: drop tab insertions, insert `{{ ocs_video_field }}` after the model group | REQ-PRD-120, REQ-PRD-122 | TEST-PRD-120 — verifies REQ-PRD-120, REQ-PRD-122 (ocmod grep in verify) | done |
| TASK-PRD-121 | Admin event/template/language/model: remove accessory code, render video field only | REQ-PRD-120, REQ-PRD-121, REQ-PRD-123, REQ-PRD-124 | TEST-PRD-121 — verifies REQ-PRD-121, REQ-PRD-123 (PHP tests) | done |
| TASK-PRD-122 | `npm run verify`, `npm run pack:theme` | REQ-PRD-124 | TEST-PRD-122 — verifies REQ-PRD-124 | done |
