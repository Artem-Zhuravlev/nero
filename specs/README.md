# Spec-Driven Development

Product code is never written from a free-form request alone — every non-trivial change is
defined here first.

```
Requirement -> Spec -> Plan -> Tasks -> Implementation -> Tests -> Verification -> Commit
```

## Hard rule

```
NO SPEC = NO IMPLEMENTATION = NO COMMIT
```

If a request is ambiguous, or asks to "just quickly add" something, stop and write or update the
spec first. If implementation reveals the spec is wrong or incomplete:

```
STOP -> UPDATE SPEC -> RE-APPROVE -> CONTINUE
```

Never silently change requirements through code.

## Layout

```
specs/<feature-name>/
├── spec.md      — problem, scope, requirements, acceptance criteria
├── plan.md      — technical approach, architecture decisions, affected files
└── tasks.md     — ordered implementation tasks, each traceable to a requirement
```

Start new specs from [`_template/`](_template/spec.md).

## Status lifecycle

| Status        | Meaning                                                      |
| ------------- | ------------------------------------------------------------ |
| `draft`       | Being written or discussed. Not implementable.               |
| `approved`    | Reviewed and signed off. Implementation may begin.           |
| `in-progress` | Implementation underway against an approved spec.            |
| `implemented` | Code complete, matches the spec, checks exist and pass.      |
| `verified`    | Implementation re-checked against the spec; considered done. |
| `deprecated`  | No longer applicable; kept for history.                      |

Only `approved` (or later) specs may be implemented. Only `verified` specs are complete.

## Traceability

Every requirement has an ID (`REQ-<AREA>-<NNN>`). Every task in `tasks.md` references the
requirement(s) it fulfills. Every automated check references the requirement it verifies
(`TEST-<AREA>-<NNN> — verifies REQ-<AREA>-<NNN>`, as a comment or in the check's name).

```
REQ-ARCH-001 -> TASK-ARCH-001 -> TEST-ARCH-001
```

## Commits

Commits reference the spec they implement:

```
feat(theme): add base theme skeleton [SPEC-003]
```

## Current specs

| ID       | Feature                                                  | Status  |
| -------- | -------------------------------------------------------- | ------- |
| SPEC-001 | [Project Architecture](project-architecture/spec.md)     | `draft` |
