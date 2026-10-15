# SPEC-020 — Agent Workflow and Knowledge Base

**Status:** draft
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-15
**Refines:** SPEC-001 REQ-ARCH-084, REQ-ARCH-160..162 · `CLAUDE.md`

## Problem

Several agents (and humans) will work on this repository over months, each starting with an empty context. The platform (ocStore/OpenCart 3) is full of non-obvious
behaviour, and the spec set is large. Without a defined workflow, agents re-derive facts, skip specs, edit core, or contradict decisions made earlier.

## Purpose

Define how an agent receives a task, loads the right context cheaply, produces specs and code, proves its work, records new knowledge and hands off — so results are
consistent regardless of which agent does the work.

## Scope

Context loading order, task intake and classification, spec-writing procedure, implementation procedure, verification and evidence, knowledge capture, handoff notes,
escalation (when to ask the owner), forbidden actions, glossary and FAQ documents.

## Out of scope

Tooling of any particular agent product; tracking tools (issues/boards).

## Functional requirements

### Context loading

- **REQ-AGT-001** — Entry point is `CLAUDE.md` (short). It must stay under 120 lines and only contain: stack summary, mandatory rules, reading order, command list, and links.
  Detail lives in `docs/` and `specs/`. Any agent instruction file for another tool (`AGENTS.md` etc.) is a one-line pointer to `CLAUDE.md`.
- **REQ-AGT-002** — Reading order for any task: (1) `CLAUDE.md`; (2) `docs/ocstore-internals.md` sections relevant to the task; (3) `docs/ocstore-reference.md` for exact
  names/values; (4) the spec(s) owning the area (use the table in `specs/README.md` and the area-to-spec map of REQ-AGT-003); (5) `docs/agent-playbook.md` recipe if one exists;
  (6) the actual core files involved (grep `core/upload/`, read the whole file being imitated).
- **REQ-AGT-003** — Area-to-spec map lives in `docs/spec-map.md` (generated from spec headers by `scripts/spec-map.mjs`): keyword/area → spec id → key REQ prefixes. Example rows:
  "permissions" → SPEC-010 (ACC) · "migration" → SPEC-005 (DB) · "theme template" → SPEC-006 (THM) · "slug/redirect" → SPEC-013 (SEO).
- **REQ-AGT-004** — Facts are verified, not recalled: claims about core behaviour cited in specs/docs carry a core file reference or the `[std]` marker; when an agent relies on
  a `[std]` fact for a decision it first verifies it in `core/upload/` and promotes it (REQ-ARCH-160).

### Task intake

- **REQ-AGT-010** — Classification of a request: (a) *question* → answer, cite files; (b) *spec work* → draft/modify spec (status `draft`); (c) *implementation of approved spec* → tasks in
  order; (d) *bug* → reproduce, locate the owning REQ; if the behaviour contradicts a requirement fix code, else update spec first; (e) *core upgrade* → SPEC-001 REQ-ARCH-100 path;
  (f) *ops/runbook* → SPEC-014. Ambiguous requests are resolved by asking the owner **once**, with the options and a recommendation.
- **REQ-AGT-011** — No spec = no implementation. If a request needs a spec that does not exist, the agent writes it (spec/plan/tasks), sets `draft`, updates `specs/README.md`, and stops
  for approval. "Approved" is set only by the owner's explicit statement in chat; agents never self-approve.
- **REQ-AGT-012** — Size gate: a change touching more than one extension or more than ~300 lines is split into tasks that each fit one commit; each task id is in the commit message.

### Spec-writing procedure

- **REQ-AGT-020** — Steps: (1) read neighbouring specs to avoid duplicate/contradicting REQs; (2) research core facts first, write a "Platform facts" section with file references;
  (3) fill the template sections; (4) every requirement is testable, has a unique id `REQ-<AREA>-<NNN>` (3 digits, next free) and states the mechanism (settings/theme/extension/event/OCMOD)
  that satisfies it; (5) write `plan.md` (decisions table with rejected alternatives) and `tasks.md` (every REQ covered); (6) run the traceability check (SPEC-009 REQ-QA-040…044);
  (7) update `specs/README.md` and the map; (8) commit `docs(spec): … [SPEC-NNN]`.
- **REQ-AGT-021** — Wording rules: normative words are MUST-style statements in plain verbs ("is", "never", "must"); no "should consider"; numbers have units; every external term is defined
  once; Ukrainian/Russian text in specs only where it is data (names, examples).
- **REQ-AGT-022** — A spec never contradicts an older approved spec silently: conflicts are listed in the new spec's "Refines/Supersedes" header and the older spec is amended in the same commit.

### Implementation procedure

- **REQ-AGT-030** — Per task: re-read the REQs and the task row; locate the nearest core example and mimic its structure; write the code in `custom/`; add the check named in the task
  (`TEST-…` id in the test name/comment); run `npm run verify` (when it exists; until then run the manual checks the task lists); update task status; commit with `[SPEC-NNN]`.
- **REQ-AGT-031** — Evidence in the final report of a task: the REQ/TASK/TEST ids touched, commands run with results (pass/fail), anything not verified and why. A task is never reported done
  with failing or skipped checks unless the report says so explicitly.
- **REQ-AGT-032** — Agents do not run destructive commands on shared data (`reset`, `DROP`, `TRUNCATE`, `rm -rf` outside `build/` and `var/`, force-push) without the owner's explicit instruction
  in the current conversation; pushes to the remote also need an explicit request each time.
- **REQ-AGT-033** — Secrets: agents never print `.env` values, DB credentials or API keys; credentials are not typed into forms or chat; test credentials are generated and stored per
  SPEC-014 and `.env`.
- **REQ-AGT-034** — Third-party code/data (vendored extensions, downloaded packs) is inspected before use (read the files), kept in its own directory, and recorded in `VENDOR.md`.

### Knowledge capture and handoff

- **REQ-AGT-040** — New platform knowledge discovered while working (a core behaviour, a pitfall, a verified value) is written to `docs/ocstore-internals.md` or `docs/ocstore-reference.md` in
  the same commit as the work, with the core file reference.
- **REQ-AGT-041** — Decisions with trade-offs go to the owning spec's `plan.md` decisions table (choice, why, rejected alternatives); open questions go to `specs/<area>/spec.md` "Open questions"
  section with an owner and a date — never only in chat.
- **REQ-AGT-042** — End-of-session handoff note (in the final message, and in `docs/handoff.md` only if work is unfinished): what changed, what is verified, what is pending, next smallest step,
  exact commands to resume. `docs/handoff.md` is deleted when the work completes.
- **REQ-AGT-043** — Recurring mistakes are added to `docs/agent-playbook.md` under "Pitfalls" (short, imperative, with the file that proves it).

### Escalation

- **REQ-AGT-050** — Ask the owner when: a decision changes a recorded decision; requirements conflict; an action is destructive or outward-facing (push, deploy, send mail, buy, publish, download
  files); a security/legal trade-off is implied (SPEC-010/015/017); the cheapest compliant option violates a hard budget (SPEC-019). Otherwise decide, record, continue.
- **REQ-AGT-051** — When asking, present: the decision, 2–3 options with consequences, a recommendation. One question per message when possible.

### Documents the workflow depends on

- **REQ-AGT-060** — Glossary `docs/glossary.md`: OCMOD, vQmod, route, event trigger, layout, module instance, extension type, total, geo zone, tax class, store, language code vs `directory`,
  `DIR_*` constants, "core", "custom", "overlay", "build", "runtime data". Each entry one or two lines with a pointer.
- **REQ-AGT-061** — FAQ `docs/faq.md`: answered questions that agents asked repeatedly (e.g. "Where do I put X?", "Why does my template change do nothing?" → refresh modifications/Twig cache/ theme editor DB
  override, "Why is my event not firing?" → trigger grammar, status, MyISAM row, handler path).
- **REQ-AGT-062** — `docs/spec-map.md` (REQ-AGT-003) and `docs/event-registry.md`, `docs/settings-registry.md`, `docs/core-touchpoints.md` are generated; agents regenerate rather than hand-edit them.
- **REQ-AGT-063** — A **map of the repository** (`docs/repo-map.md`, generated): directory tree to depth 3 with one-line purposes taken from `README`-style headers; refreshed by `verify`.

### Subagents / parallel work

- **REQ-AGT-070** — Parallel agents must not edit the same spec or extension; each works on separate `specs/<area>` or `custom/extensions/<code>` directories and commits separately; shared files
  (`specs/README.md`, `docs/*-registry.md`, `CLAUDE.md`) are edited by one agent at the end.
- **REQ-AGT-071** — Read-only research agents return findings with file references, not summaries without sources; the requesting agent verifies the two or three claims that its decisions depend on.

## Non-functional requirements

- **REQ-AGT-080** — A new agent can find the answer to "where does X go?" in ≤ 3 file reads using the decision tree in the playbook and the spec map.
- **REQ-AGT-081** — Docs avoid duplication: each fact has one home (internals = how it works; reference = exact values; specs = rules; playbook = procedures); others link.

## Data requirements

Docs only.

## Edge cases

- Two specs give different values for one setting → settings registry generator fails; fix specs.
- Agent cannot run PHP/Node → reports the verification gap; does not claim success.
- Owner message conflicts with a spec → follow the owner for this task, then propose the spec amendment (REQ-AGT-022).
- Task spans repos (e.g. a vendored extension upstream) → only this repo is modified; upstream changes are documented.

## Acceptance criteria

- [ ] `CLAUDE.md` ≤ 120 lines with the reading order and links to every doc named here.
- [ ] `docs/glossary.md`, `docs/faq.md`, `docs/spec-map.md`, `docs/repo-map.md` exist; generators run in `verify`.
- [ ] A dry-run "new agent" exercise: given 5 sample tasks, the playbook + spec map lead to the right spec in each case (recorded in `docs/agent-dryrun.md`).
- [ ] Handoff template present and used.
