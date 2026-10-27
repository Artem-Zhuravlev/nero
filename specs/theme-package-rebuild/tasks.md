# TASKS — SPEC-051 Rebuild the Theme Package After Every Change

| Task         | Description                                                        | Fulfills                       | Check        | Status  |
| ------------ | ------------------------------------------------------------------ | ------------------------------ | ------------ | ------- |
| TASK-PKG-001 | Export the packaged source list from `lib/pack-theme.mjs`          | REQ-PKG-002, REQ-PKG-004       | TEST-PKG-001 | pending |
| TASK-PKG-002 | `--check` mode + `pack:theme:check` script                         | REQ-PKG-002, REQ-PKG-005       | TEST-PKG-001 | pending |
| TASK-PKG-003 | Node test: missing / stale / fresh / excluded-file cases           | REQ-PKG-002                    | TEST-PKG-001 | pending |
| TASK-PKG-004 | Add the check to `scripts/verify.mjs`                              | REQ-PKG-003                    | TEST-PKG-002 | pending |
| TASK-PKG-005 | Write the rule into `CLAUDE.md` and the playbook done-checklist    | REQ-PKG-001                    | TEST-PKG-003 | pending |

TEST-PKG-001 — verifies REQ-PKG-002, REQ-PKG-004, REQ-PKG-005: node test of the check.
TEST-PKG-002 — verifies REQ-PKG-003: `npm run verify` fails on a stale archive, passes on a fresh one.
TEST-PKG-003 — verifies REQ-PKG-001: grep that both docs contain the `pack:theme` rule.
