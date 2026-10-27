# TASKS — SPEC-070 "Already in cart" Button State

| Task         | Description                                             | Fulfills                       | Check        | Status  |
| ------------ | ------------------------------------------------------- | ------------------------------ | ------------ | ------- |
| TASK-INC-001 | `text_in_cart_btn` string (uk/ru/en) + events.php       | REQ-INC-003, 004               | TEST-INC-001 | done    |
| TASK-INC-002 | `markCart()`: text per button type, `.is-just-added`    | REQ-INC-001, 003, 004, 005     | TEST-INC-002 | done    |
| TASK-INC-003 | CSS: check draw animation, pop, reduced-motion          | REQ-INC-001, 002, 006          | TEST-INC-003 | done    |
| TASK-INC-004 | Verify, specs index, `npm run pack:theme`               | REQ-INC-007                    | TEST-INC-004 | done    |

- **TEST-INC-001** — verifies REQ-INC-003, 004: language files of all three locales define both keys.
- **TEST-INC-002** — verifies REQ-INC-001, 003, 005: in the browser, add from card and product page; texts and reload state are right.
- **TEST-INC-003** — verifies REQ-INC-001, 002: check animates once after a click, not on load; none with reduced motion.
- **TEST-INC-004** — verifies REQ-INC-006, 007: `npm run verify`, `npm test` green; archive rebuilt.
