# PLAN — SPEC-020 Agent Workflow

## Approach

Make the repository self-describing: short entry file, layered docs with one home per fact, generated indexes (spec map, repo map, registries), and rules for task intake,
evidence and handoff. Existing docs (`ocstore-internals`, `ocstore-reference`, `agent-playbook`, `coding-rules`) are the base; glossary/FAQ/maps are added.

## Decisions

| Decision | Choice | Why |
| -------- | ------ | --- |
| Entry file size | ≤ 120 lines | loaded into every session; detail on demand |
| Source of truth per fact | one doc | avoids drift |
| Generated indexes | scripts, not hand edits | stay accurate |
| Approval | owner statement only | agents must not self-approve |
| Questions | once, with options + recommendation | minimal interruption |

## Affected files

`CLAUDE.md`, `docs/{glossary,faq,spec-map,repo-map,handoff,agent-dryrun}.md`, `scripts/spec-map.mjs`, `scripts/repo-map.mjs`.

## Risks

- Generated docs go stale if not part of verify → included in `verify` as "docs-fresh" check.
- Over-long CLAUDE.md → line-count check.
