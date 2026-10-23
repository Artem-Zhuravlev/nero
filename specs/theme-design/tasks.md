# TASKS — SPEC-023 Storefront Visual Design

Prerequisite: SPEC-006 approved and TASK-THM-001..006 done.

| Task | Description | Fulfills | Check | Status |
| ---- | ----------- | -------- | ----- | ------ |
| TASK-DSG-001 | Token block (colour, type, spacing, radius, shadow, motion), light + dark | REQ-DSG-001..006 | TEST-DSG-001 | done |
| TASK-DSG-002 | Contrast test over all token pairs (Node script in verify) | REQ-DSG-002, 003, 004 | TEST-DSG-002 | done |
| TASK-DSG-003 | Self-hosted Inter (latin + cyrillic) | REQ-DSG-005 | TEST-DSG-003 | pending |
| TASK-DSG-004 | Buttons, forms, alerts, breadcrumbs, pagination, tabs, tables | REQ-DSG-010, 011, 015 | TEST-DSG-004 | in-progress |
| TASK-DSG-005 | `OCS.modal` + `.ocs-modal` styles (overlay/Esc close, focus trap) | REQ-DSG-013 | TEST-DSG-005 | done |
| TASK-DSG-006 | Badge component and sticker slots | REQ-DSG-014, 012 | TEST-DSG-006 | in-progress |
| TASK-DSG-007 | Header/footer/menu templates with slots | REQ-DSG-020, 021 | TEST-DSG-007 | in-progress |
| TASK-DSG-008 | Home, category, product, thumb templates | REQ-DSG-012, 022, 023, 024 | TEST-DSG-008 | pending |
| TASK-DSG-009 | Blog templates styling | REQ-DSG-025 | TEST-DSG-009 | pending |
| TASK-DSG-010 | `vendor-overrides.css` for OCFilter/Simple | REQ-DSG-026 | review | pending |
| TASK-DSG-011 | uk/ru/en theme strings | REQ-DSG-031 | TEST-DSG-011 | pending |
| TASK-DSG-012 | Image-size migration | REQ-THM-030 | TEST-DSG-012 | pending |
| TASK-DSG-013 | `DESIGN.md` (tokens, slots, re-brand guide) + no-colour-literal grep check | REQ-DSG-001, 032, 042 | TEST-DSG-001 | done |
| TASK-DSG-014 | Responsive/budget/browser audit | REQ-DSG-040, 041 | manual | pending |
| TASK-DSG-015 | PHP 7.4 compatibility of all PHP added by this spec (no 8.x syntax; ocStore runs on 7.4 max) | REQ-DSG-030 | review | pending |
