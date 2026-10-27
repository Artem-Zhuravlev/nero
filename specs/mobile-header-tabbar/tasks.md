# TASKS — SPEC-059 Mobile Header Reduction and Tab Bar Order

| Task         | Description                                                        | Fulfills                                   | Check        | Status |
| ------------ | ------------------------------------------------------------------ | ------------------------------------------ | ------------ | ------ |
| TASK-MHT-001 | CSS: hide util strip and header actions on mobile, new head grid    | REQ-MHT-001                                | TEST-MHT-001 | done   |
| TASK-MHT-002 | JS: move util controls into the mobile menu panel and back          | REQ-MHT-002, REQ-MHT-003, REQ-MHT-006      | TEST-MHT-002 | done   |
| TASK-MHT-003 | Panel styles for the moved links, contacts, language and currency   | REQ-MHT-002, REQ-MHT-007                   | TEST-MHT-002 | done   |
| TASK-MHT-004 | Tab bar order in `footer.twig`                                      | REQ-MHT-004                                | TEST-MHT-003 | done   |
| TASK-MHT-005 | Cart tab badge (markup, CSS, JS count)                              | REQ-MHT-005                                | TEST-MHT-003 | done   |
| TASK-MHT-006 | `npm run verify`, `npm run pack:theme`, README row, commit [SPEC-059] | all                                      | TEST-MHT-004 | done   |

- **TEST-MHT-001** — verifies REQ-MHT-001: browser check at 375px, header shows logo, search, catalogue button only.
- **TEST-MHT-002** — verifies REQ-MHT-002, REQ-MHT-003, REQ-MHT-006, REQ-MHT-007: panel shows links/contacts/language/currency, switching works, resize round trip.
- **TEST-MHT-003** — verifies REQ-MHT-004, REQ-MHT-005: tab order and cart badge after add-to-cart.
- **TEST-MHT-004** — verifies all: `npm run verify` green, archive rebuilt.
