# PLAN — SPEC-051 Rebuild the Theme Package After Every Change

## Approach

Add a `--check` mode to `scripts/pack-theme.mjs` that reuses the source file list from `scripts/lib/pack-theme.mjs`
(same inclusion/exclusion rules as the real pack) and compares the newest source mtime with the archive mtime.
Expose it as `npm run pack:theme:check`, call it from `scripts/verify.mjs`, and document the "rebuild after every
change" rule for agents in `CLAUDE.md` and the playbook checklist.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Enforcement | agent rule + staleness check in `verify` | A Claude Code hook on every edit would rebuild dozens of times per task; Git hooks are out of scope |
| Staleness test | mtime comparison | Fast and dependency-free; hashing would need a manifest file |

## Affected files / directories

- `scripts/pack-theme.mjs`, `scripts/lib/pack-theme.mjs` (export the source list)
- `scripts/verify.mjs`, `package.json`
- `scripts/test/` (node test for the check)
- `CLAUDE.md`, `docs/agent-playbook.md`, `specs/README.md`

## Risks

- mtime is reset by a fresh checkout -> the check is a local-dev guard; it fails -> run `pack:theme`.
