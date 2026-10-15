# oc-store-setup — instructions for Claude

Stack: **ocStore 3.0.3.7** (OpenCart 3.0.3.x fork, PHP + MySQL/MariaDB, Twig templates, MVC-L)
as an unmodified base in `core/`, with all project code in `custom/`, composed into a runnable
docroot by `scripts/build.mjs`. Dev tooling: Node >= 20 (build/verify) and PHP CLI (install,
migrate, seed).

## Spec-driven development (mandatory)

Read `specs/README.md`. Rule: **NO SPEC = NO IMPLEMENTATION = NO COMMIT.**

1. For any non-trivial change, first create `specs/<feature>/{spec,plan,tasks}.md` from
   `specs/_template/` with the next free `SPEC-NNN` id, status `draft`.
2. Stop and get the user's approval -> set status `approved`. Do not implement before that.
3. Implement task by task (status `in-progress`); every requirement has `REQ-<AREA>-NNN`,
   every task references REQs, every test/check has a `TEST-<AREA>-NNN — verifies REQ-…` id.
4. If the spec turns out wrong: stop, update the spec, re-approve, continue.
5. Finish with `npm run verify` green -> status `implemented`; add the row to the table in
   `specs/README.md`. Commit messages end with `[SPEC-NNN]`.

## Read first (platform context)

1. `docs/ocstore-internals.md` — how ocStore/OpenCart 3 works: request lifecycle, routes,
   loader/events, Twig (autoescape is OFF), themes, OCMOD, extensions, DB tables, pitfalls.
2. `docs/agent-playbook.md` — decision tree "where does my change go" + recipes (module, event,
   theme override, OCMOD, migration, new page, language strings) and the done-checklist.
3. `docs/ocstore-reference.md` — verified tables: generated config constants, setting keys,
   extension contracts, layouts, default events, DB engines.
4. `docs/coding-rules.md` — style and layering rules.

Architecture is split into SPEC-001 (foundation) and refinements SPEC-003 build/config, 004
extensions, 005 database/migrations, 006 theme, 007 OCMOD/events, 008 localisation, 009 quality
gates, 010 admin access, 011 orders/checkout, 012 catalogue data, 013 SEO/search, 014 operations, 015 customer accounts, 016 content/blog, 017 marketing/analytics/returns, 018 integrations, 019 performance/availability, 020 agent workflow, 021 testing, 022 multi-store/environments.
Also read `docs/glossary.md` and `docs/faq.md` when terms or symptoms are unclear.
When a rule seems missing, look in the matching spec before inventing one.

Verify claims against `core/upload/` by grepping before relying on memory of "standard"
OpenCart — this is a fork (ocStore) with blog, SeoPro and other changes.

The architecture is fixed by **SPEC-001** (`specs/project-architecture/spec.md`). Read it before
touching anything. The non-negotiable parts:

- `core/` is **read-only**. Never edit a file under `core/upload/`. Not even "just one line".
- All project code lives in `custom/` and reaches the store only through the build overlay,
  OCMOD, events, or a theme/extension — never by overwriting a core file.
- All database changes are numbered idempotent migrations in `custom/db/migrations/`.
  Never change the schema or settings by hand in phpMyAdmin/admin and leave it undocumented.
- Secrets live only in `.env` (git-ignored). `system/storage` lives outside the docroot.
- Controllers stay thin; logic goes in `system/library/ocs/*` classes or models.

## Commands

Implemented so far (SPEC-001/003/009, see task statuses): `npm run core:check` (core/ vs manifest), `npm run build` /
`npm run build:check` (compose `build/www` + storage from `.env`; needs `.env` copied from `.env.example`), `npm test`
(Node tests in `scripts/test/`), `npm run verify` (core-manifest, layout, php-lint, build-check, traceability, secrets).
Not implemented yet: migrations, installer, extension scaffolder, smoke tests, jobs — do not invent them; implement from the
approved specs. Nothing is deployed from this repo.

- Docs: `docs/ocstore-internals.md`, `docs/agent-playbook.md`, `docs/coding-rules.md`,
  `docs/architecture-overview.md`
