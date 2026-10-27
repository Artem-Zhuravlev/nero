# TASKS — SPEC-073 Product Gallery Stickers Top Right

| Task         | Description                                   | Fulfills    | Check        | Status |
| ------------ | --------------------------------------------- | ----------- | ------------ | ------ |
| TASK-PGS-001 | Merge `tl` + `tr` into the top-right slot     | REQ-PGS-001 | TEST-PGS-001 | done   |
| TASK-PGS-002 | Verify, specs index, `npm run pack:theme`     | REQ-PGS-002 | TEST-PGS-002 | done   |

- **TEST-PGS-001** — verifies REQ-PGS-001: on a product with a discount sticker, it is in the gallery's top-right corner, not over a thumbnail.
- **TEST-PGS-002** — verifies REQ-PGS-002: `npm run verify` green; archive rebuilt.
