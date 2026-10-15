# PLAN — SPEC-007 OCMOD and Event Policy

## Approach

Implement a reference OCMOD applier in Node (`scripts/lib/ocmod-apply.mjs`) mirroring core's
`marketplace/modification/refresh` semantics (read from `admin/controller/marketplace/
modification.php` when implementing: search/regex/trim/index, positions, offset, file globs
and braces, `error` attribute). It serves verification and `core-impact`. The authoritative runtime
applier is core's own (REQ-DB-040 refresh).

## Decisions

| Decision | Choice | Why |
| -------- | ------ | --- |
| Prefer events | yes, with strict grammar | detectable, no source patching |
| OCMOD verification | apply to pristine core + `php -l` | catches drift at upgrade time |
| Registry docs | generated/checked | prevents silent dead events |
| Priority bands | 100–899 for ours | stay between core's 0/500/998/999/1000 |

## Affected files

`scripts/lib/ocmod-apply.mjs`, `scripts/core-impact.mjs`, `docs/event-registry.md`,
`docs/core-touchpoints.md` (generated), `custom/ocmod/*`.

## Risks

- Node port may diverge from PHP applier semantics -> compare against admin refresh output in a
  fixture test (TASK-OCM-004).
