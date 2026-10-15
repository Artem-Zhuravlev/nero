# Coding Rules

## Spec-driven development

See [`specs/README.md`](../specs/README.md). Master rule; everything below is secondary.

## Core is sacred

- Never edit `core/`. Customise by: settings -> theme -> extension -> event -> OCMOD
  (SPEC-001 section 4). A stronger mechanism needs a justification in the spec's `plan.md`.
- Never shadow a core file in `custom/overlay`; the build rejects it.

## PHP (custom code)

- Follow OpenCart naming for controllers/models (class derived from route), PSR-12 formatting
  otherwise (4 spaces *in new files*; tabs are only used in core).
- Controllers: read request, call model/library, set Twig data, render. No SQL, no business rules.
- Models: SQL only; cast/escape every value; one model per aggregate.
- Libraries (`system/library/ocs/`): `namespace Ocs;`, constructor takes `$registry`, no
  superglobals, no output, no `header()`.
- No `@`, `eval`, `die`, `exit` (outside scripts), `$GLOBALS`, global `define`.
- No hard-coded user-facing text: language files only, all enabled languages.
- No magic numbers/strings: named constants or settings.
- Don't add abstractions or options for requirements that don't exist yet.

## Twig / frontend

- Templates: no business logic, no SQL; escape by default; `|raw` only for admin-authored HTML
  with a comment.
- CSS/JS live in the theme, one stylesheet entry point per theme; no inline styles/scripts
  except to pass data.

## SQL

- Migrations only; idempotent; `{{PREFIX}}`; never edit an applied one; no core DDL (SPEC-001
  REQ-ARCH-053); custom tables `oc_ocs_*`, InnoDB, `utf8`.

## Security

- Escape DB input, check `modify` permission + `user_token` in admin actions, never log
  secrets, never put secrets in git.

## Git

- `type(scope): message [SPEC-NNN]`, commit on `main`, push only when the owner says so.
