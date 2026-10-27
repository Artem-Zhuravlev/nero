# SPEC-051 — Rebuild the Theme Package After Every Change

**Status:** draft
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-040 (theme package `ocs-theme-<version>.ocmod.zip`), SPEC-020 (agent workflow)

## Problem

The installable archive `dist/ocs-theme-1.0.0.ocmod.zip` is produced by `npm run pack:theme` (SPEC-040), but
nothing makes sure it is rebuilt. After CSS/template/extension edits the archive in `dist/` is stale, and a
stale archive gets installed on the store without the latest fixes.

## Purpose

After every change that affects the contents of the theme package, the archive is rebuilt, so `dist/` always
matches `custom/`.

## Scope

- A rule for agents: a change under the packaged paths of `custom/` is not finished until `npm run pack:theme` ran.
- A staleness check (`npm run pack:theme:check`) that fails when the archive is older than its sources.
- `npm run verify` includes that check.

## Out of scope

- Changing what goes into the archive or its version (`custom/VERSION`, SPEC-040).
- Committing `dist/` (stays git-ignored), deployment or upload of the archive.
- Git hooks, CI.

## Functional requirements

- **REQ-PKG-001** — After any change to files that `scripts/lib/pack-theme.mjs` packages (theme, extensions,
  migrations, OCMOD, demo catalogue, `custom/VERSION`), the agent runs `npm run pack:theme` before reporting the
  task done. The rule is written into `CLAUDE.md` and `docs/agent-playbook.md` (done-checklist).
- **REQ-PKG-002** — `npm run pack:theme:check` exits non-zero with a clear message when
  `dist/ocs-theme-<VERSION>.ocmod.zip` is missing or older than the newest packaged source file, and exits 0 otherwise.
  It does not write anything.
- **REQ-PKG-003** — `npm run verify` runs the check from REQ-PKG-002 as one of its steps, reported as a warning-free
  pass/fail like the other steps.
- **REQ-PKG-004** — The rebuild keeps the existing guarantees of SPEC-040 (no secrets, allowed prefixes only);
  the output file name stays `ocs-theme-<VERSION>.ocmod.zip`.

## Non-functional requirements

- **REQ-PKG-005** — The check is fast (mtime comparison, no zip reading) and dependency-free (Node >= 20).

## Data requirements

None. Reads `custom/**` and `dist/`.

## Interfaces

- `npm run pack:theme` (existing), `npm run pack:theme:check` (new), `scripts/pack-theme.mjs --check`.

## Edge cases

- `dist/` does not exist -> check fails with "run npm run pack:theme".
- `custom/VERSION` bumped -> the old-version archive is ignored; the new name is missing -> check fails.
- Files under `tests/` and `.gitkeep` (excluded from the package) -> do not make the archive stale.

## Acceptance criteria

- [ ] Editing any packaged file makes `pack:theme:check` fail; after `pack:theme` it passes.
- [ ] Editing an excluded file (`tests/`, `.gitkeep`) does not make it fail.
- [ ] `npm run verify` fails on a stale archive and passes on a fresh one.
- [ ] `CLAUDE.md` and the agent playbook checklist state the rebuild rule.
