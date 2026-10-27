# TASKS — SPEC-072 Footer Menu Visibility, Merged Column, Payment Logos

| Task         | Description                                                        | Fulfills                   | Check        | Status  |
| ------------ | ------------------------------------------------------------------ | -------------------------- | ------------ | ------- |
| TASK-FMN-001 | `Ocs\Footer`: sanitize + groups (merged column, hiding)            | REQ-FMN-001, 003, 004, 009 | TEST-FMN-001 | done    |
| TASK-FMN-002 | Footer data in the display event; setting in `extension.json`      | REQ-FMN-001, 008           | TEST-FMN-002 | done    |
| TASK-FMN-003 | `footer.twig`: render groups from data, payment logos              | REQ-FMN-003, 004, 006      | TEST-FMN-002 | done    |
| TASK-FMN-004 | CSS: even columns, full width without contacts, bottom row, logos  | REQ-FMN-005, 006           | TEST-FMN-003 | done    |
| TASK-FMN-005 | Admin form fieldset, controller sanitising, uk-ua / en-gb strings  | REQ-FMN-001, 002, 007      | TEST-FMN-002 | done    |
| TASK-FMN-006 | Verify, specs index row, `npm run pack:theme`                      | REQ-FMN-008, 009           | TEST-FMN-004 | done    |

- **TEST-FMN-001** — verifies REQ-FMN-001, 003, 004: `FooterTest.php` — unknown keys dropped, hidden links and empty groups removed, merged group order, missing setting shows everything.
- **TEST-FMN-002** — verifies REQ-FMN-002, 006, 007, 008: Node test — form fieldset, twig, language keys and `extension.json` wired.
- **TEST-FMN-003** — verifies REQ-FMN-005, 006: in the browser at 1280 / 768 / 360 px with all, some and no groups hidden; logos on/off.
- **TEST-FMN-004** — verifies REQ-FMN-009: `npm run verify` green; archive rebuilt.
