# TASKS — SPEC-076 Image Resize Guard

| Task         | Description                                  | Fulfills                 | Check        | Status      |
| ------------ | -------------------------------------------- | ------------------------ | ------------ | ----------- |
| TASK-IRG-001 | OCMOD patch for ModelToolImage::resize()      | REQ-IRG-001, REQ-IRG-004 | TEST-IRG-001 | done        |
| TASK-IRG-002 | Migration 0010 installs the OCMOD             | REQ-IRG-002              | TEST-IRG-002 | done        |
| TASK-IRG-003 | Migration 0008 per-key setting delete         | REQ-IRG-003              | TEST-IRG-003 | done        |
| TASK-IRG-004 | Verify, specs index, `npm run pack:theme`     | REQ-IRG-004              | TEST-IRG-004 | done        |

- **TEST-IRG-001** — verifies REQ-IRG-001, REQ-IRG-004: OCMOD XML is well-formed and its search string exists in core `catalog/model/tool/image.php`.
- **TEST-IRG-002** — verifies REQ-IRG-002: migration 0010 passes php-lint and is idempotent in the PHP test runner.
- **TEST-IRG-003** — verifies REQ-IRG-003: 0008 passes php-lint; the loop deletes each key.
- **TEST-IRG-004** — verifies REQ-IRG-004: `npm run verify` green; archive rebuilt.
