# SPEC-005 — Database, Migrations and Settings-as-Code

**Status:** approved
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-15
**Refines:** SPEC-001 section 8 · **Facts:** [`docs/ocstore-reference.md`](../../docs/ocstore-reference.md) §3, §5, §6, §9

## Problem

ocStore has no migration framework, config lives in `oc_setting` rows, permissions in a JSON
column, extension registration in `oc_extension`, events in `oc_event`, layouts and SEO slugs in
separate tables — and almost all core tables are MyISAM (no transactions). Reproducible
environments need a disciplined, idempotent mechanism with helpers that encapsulate these quirks.

## Purpose

Specify the migration runner, the migration file formats, the helper API (settings, extensions,
permissions, events, SEO URLs, layouts, modules, OCMOD), seeds, ordering, checksums, backups and
the verification of schema/data invariants.

## Scope

`scripts/migrate.php`, `scripts/seed.php`, `scripts/refresh.php`, helper library, file
conventions, table `oc_ocs_migration`, naming of custom tables.

## Out of scope

Concrete schema/data of business features (their specs), backup tooling internals (SPEC-001
REQ-ARCH-056), hosting.

## Functional requirements

### Runner

- **REQ-DB-001** — `php scripts/migrate.php [up|status|down <ext-code>] [--dry-run] [--to <id>]`.
  `up` (default) applies every pending migration in order; `status` prints applied/pending/
  changed; `down <code>` runs that extension's `*_uninstall` migration (REQ-EXT-050).
- **REQ-DB-002** — The runner connects with core's own `DB` class
  (`system/library/db.php`, driver `mysqli`) using the generated `config.php` constants — never
  with a separate driver — so charset/timezone behaviour is identical to the store
  (`SET time_zone` as in `framework.php`).
- **REQ-DB-003** — Bookkeeping table (created by the runner on first use, `CREATE TABLE IF NOT
  EXISTS`):
  ```sql
  CREATE TABLE {{PREFIX}}ocs_migration (
    id VARCHAR(96) NOT NULL PRIMARY KEY,   -- e.g. core:0003_store_settings or ext:ocs_hello:001_install
    checksum CHAR(64) NOT NULL,            -- sha256 of file bytes (LF-normalised)
    applied_at DATETIME NOT NULL,
    duration_ms INT NOT NULL,
    status ENUM('applied','failed') NOT NULL DEFAULT 'applied',
    note VARCHAR(255) NOT NULL DEFAULT ''
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
  ```
- **REQ-DB-004** — Order of application: all `core:*` ids ascending, then extension migrations
  grouped per extension in dependency order (`requires.extensions`, REQ-EXT-004), within an
  extension ascending by number. `core:` migrations are those in `custom/db/migrations/`.
- **REQ-DB-005** — A migration is recorded **after** it completes. A failure records
  `status='failed'` with the message (truncated to 255) and stops the run (exit 1). The next
  `up` re-runs failed migrations from the start — therefore **every migration must be safe to
  re-run** (REQ-DB-012). No attempt is made to roll back (MyISAM has no transactions).
- **REQ-DB-006** — Checksum rule: if an applied migration's file checksum differs -> abort
  (exit 3) listing the id. `--accept-checksum <id>` exists only for whitespace-only fixes and
  requires `APP_ENV=local`.
- **REQ-DB-007** — `--dry-run` prints the ordered list and, for `.sql` files, the statements; it
  executes nothing and does not create the bookkeeping table.
- **REQ-DB-008** — Concurrency: the runner takes a MySQL advisory lock
  (`GET_LOCK('ocs_migrate', 0)`); a second runner exits with code 4.
- **REQ-DB-009** — Before `up` in any environment other than `local`, the runner requires a
  backup taken in the last 24 h (`var/backups/*/manifest.json`) unless `--no-backup-check`.

### File formats

- **REQ-DB-010** — Core-level migrations: `custom/db/migrations/NNNN_<slug>.(sql|php)`, `NNNN` four
  digits, `<slug>` `[a-z0-9_]+`, numbers strictly increasing with no gaps or duplicates.
  Extension migrations: `custom/extensions/<code>/db/NNN_<slug>.(sql|php)`, three digits.
- **REQ-DB-011** — `.sql` files: UTF-8 no BOM, LF, statements separated by `;` at line end,
  `--` comments, `{{PREFIX}}` token for the table prefix, optional `{{STORE_ID}}` (default 0),
  `{{LANG:<code>}}` (language_id lookup, e.g. `{{LANG:uk-ua}}`) and `{{GROUP:Administrator}}`.
  No `DELIMITER` blocks, no stored procedures/triggers.
- **REQ-DB-012** — Idempotence patterns (mandatory):
  - tables: `CREATE TABLE IF NOT EXISTS`
  - columns on **our** tables: guarded through helper (`$m->addColumnIfMissing`) in a `.php` file
  - settings: through the helper (REQ-DB-020), never raw `INSERT` (no unique key exists)
  - rows keyed by natural key: `INSERT … SELECT … FROM DUAL WHERE NOT EXISTS (…)`
  - destructive statements (`DROP`, `TRUNCATE`, `DELETE` without a code-scoped `WHERE`) are
    forbidden outside `*_uninstall` files; lint enforces.
- **REQ-DB-013** — `.php` files return a callable: `return function (\Ocs\Migrate\Context $m): void { … };`
  with **no side effects at include time**, no `exit`, no echo (use `$m->log()`).
  Declared `strict_types=1`.
- **REQ-DB-014** — Seeds: `custom/db/seed/NNNN_<slug>.(sql|php)` same formats and rules, run by
  `php scripts/seed.php`, tracked in the same table with id prefix `seed:`; refused when
  `APP_ENV` is `staging` or `production` (REQ-ARCH-052).

### Helper API (`\Ocs\Migrate\Context` in `scripts/lib/php/`)

All helpers are idempotent and log what they changed (`created|updated|unchanged`).

- **REQ-DB-020** — **Settings.** `$m->setting->set(string $code, string $key, mixed $value, int $store = 0)`
  (arrays/objects stored JSON with `serialized=1`); `get`, `delete`; `setMany($code, array, $store)`
  replicating `editSetting` semantics **only for the given keys** (does not wipe other keys of the
  same `code`, unlike core's `editSetting` — documented difference). Implementation: `UPDATE …
  WHERE store_id/code/key`; if no row affected and none exists -> `INSERT`; duplicates of the
  same key (possible in core data) are reduced to one.
- **REQ-DB-021** — **Extensions.** `$m->extension->register(string $type, string $code)` (insert into
  `oc_extension` if missing), `unregister`, `isRegistered`.
- **REQ-DB-022** — **Permissions.** `$m->permission->grant(int|string $group, string $route, array $types =
  ['access','modify'])` reads the JSON of `oc_user_group.permission`, adds the route to each list
  if absent, writes back (same effect as core `addPermission`, without duplicates); `revoke`.
  Group by id or by name (`'Administrator'` = id 1).
- **REQ-DB-023** — **Events.** `$m->event->register(string $code, string $trigger, string $action, int
  $sort = 0, int $status = 1)`: ensures exactly one row with that `code` and `trigger`, `action`
  updated if changed. Validates the trigger against `^(catalog|admin)/(controller|model|view|language)/[a-zA-Z0-9_/]+/(before|after)$`
  (view/language/controller routes may contain `*` wildcard) and that `action` route's file
  exists in an extension or core. `unregisterByCode($code)`. Codes must start with `ocs_`.
- **REQ-DB-024** — **SEO URLs.** `$m->seoUrl->set(string $query, string $keyword, string $lang, int $store = 0)`
  ensures one row per `(store, language, query)`; fails if the `keyword` is already used by a
  different `query` in the same `(store, language)`; `delete($query)`.
- **REQ-DB-025** — **Layouts.** `$m->layout->routeAdd(int $layoutId, string $route, int $store = 0)`;
  `$m->layout->moduleAdd(int $layoutId, string $moduleCode, string $position, int $sort)` where
  `$moduleCode` is `<code>` or `<code>.<module_id>`; both de-duplicate; layout referenced by
  **route** (`product/category`) through `$m->layout->idByRoute()` rather than by magic id when
  possible (reference §6 lists default ids).
- **REQ-DB-026** — **Module instances.** `$m->module->upsert(string $code, string $name, array
  $setting): int` (by `code`+`name`), returns `module_id`.
- **REQ-DB-027** — **OCMOD.** `$m->ocmod->install(string $xmlPath)` parses code/name/author/version/link
  from the XML, inserts or updates the `oc_modification` row keyed by `code` with
  `extension_install_id = 0`, `status = 1`, `xml` = file content; `remove($code)`. After any
  OCMOD change the runner sets a flag so `refresh.php` runs (REQ-DB-040).
- **REQ-DB-028** — **Lookup.** `$m->lang->id('uk-ua')`, `$m->currency->id('UAH')`, `$m->group->id('Administrator')`,
  `$m->orderStatus->id('Pending')`: fail loudly if missing (never guess ids).
- **REQ-DB-029** — **Raw SQL.** `$m->sql(string $sql, array $params = [])` expands `{{PREFIX}}`
  and escapes `?` params with `DB::escape`; `$m->query`/`$m->rows` for reads. Direct string
  concatenation of values is a lint error.
- **REQ-DB-030 Schema helpers (own tables only)** — `createTable`, `addColumnIfMissing`,
  `addIndexIfMissing`, `dropTableIfExists` (uninstall only). Each refuses a table name that does
  not start with `{{PREFIX}}ocs_`.

### Refresh step

- **REQ-DB-040** — `php scripts/refresh.php` after migrations that touched OCMOD/events/extensions:
  (1) empties `DIR_MODIFICATION` except `index.html`; (2) rebuilds patched files exactly like
  core `marketplace/modification/refresh` for all `status=1` rows (implementation reuses the
  core algorithm by invoking that controller method through a minimal admin bootstrap
  **or**, if that is infeasible, by a faithful port recorded with a test comparing output with the
  admin-triggered result); (3) clears `DIR_CACHE` (except `index.html`) and the Twig cache.
  The runner prints "refresh required" if it was skipped.
- **REQ-DB-041** — Refresh logs each OCMOD operation result; any `abort`-level failure exits
  non-zero with the OCMOD code and file.

### Naming and structure of custom tables

- **REQ-DB-050** — Custom tables: `{{PREFIX}}ocs_<ext>_<entity>`; PK `<entity>_id INT(11) AUTO_INCREMENT`;
  multi-language text in `<table>_description (<entity>_id, language_id, …, PRIMARY KEY (<entity>_id, language_id))`;
  store links in `<table>_to_store (<entity>_id, store_id)`; `date_added DATETIME NOT NULL`,
  `date_modified DATETIME NOT NULL` where relevant; `status TINYINT(1) NOT NULL DEFAULT 1` where
  relevant; `sort_order INT(3) NOT NULL DEFAULT 0`; engine InnoDB, charset utf8 (SPEC-001 REQ-ARCH-055).
- **REQ-DB-051** — Indexes: every column used in `WHERE`/`JOIN` by a custom query has an index
  (declared in the migration); no index on low-cardinality flags alone.
- **REQ-DB-052** — No foreign key constraints to core (MyISAM); referential integrity between our own
  InnoDB tables may use FKs with `ON DELETE CASCADE` and is declared in the migration.
- **REQ-DB-053** — Deleting a business entity from admin removes its rows from every related
  custom table in the same model method (core convention) — covered by unit test.

### Verification

- **REQ-DB-060** — `verify` checks: numbering (REQ-DB-010), forbidden statements (REQ-DB-012),
  `.php` shape (REQ-DB-013), table prefix rules (REQ-DB-030), token validity, and—when a DB is
  reachable—that `migrate --dry-run` shows nothing pending after `up` and that `up` run twice
  changes nothing (idempotence test).
- **REQ-DB-061** — A schema snapshot test dumps `SHOW CREATE TABLE` for all `oc_ocs_*` tables after
  `up` and compares to `custom/db/schema.snapshot.sql` (committed); intentional changes update
  it in the same commit.

## Non-functional requirements

- **REQ-DB-070** — Runner needs only PHP + `mysqli`; no Composer.
- **REQ-DB-071** — A full `up` on an empty installed DB with the core migrations only takes < 10 s.
- **REQ-DB-072** — Credentials come from the generated `config.php`; the runner never prints them.

## Data requirements

`{{PREFIX}}ocs_migration` (REQ-DB-003). No other tables from this spec.

## Edge cases

- Duplicate `(code,key)` rows already in `oc_setting` -> `set` keeps the lowest `setting_id`, deletes extras.
- `oc_user_group.permission` is empty string/invalid JSON -> treated as `{}`; runner logs a warning.
- Migration number reused after rename -> checksum/ID mismatch abort.
- Installing a language not yet present -> `{{LANG:xx}}` fails with message naming the migration
  that must create the language first.
- Trigger string for admin events accidentally starts with `catalog/` -> lint/helper rejects when
  the action's controller exists only under `admin/`.
- Table prefix other than `oc_` -> all tokens expand correctly; hard-coded `oc_` in a migration is a lint error.

## Acceptance criteria

- [ ] `migrate up` on a fresh installed DB creates `oc_ocs_migration` and applies baseline.
- [ ] Second `up` reports nothing to do; `status` shows all applied, none changed.
- [ ] Editing an applied file makes `up` exit 3.
- [ ] Simulated failure in the middle of a `.php` migration leaves `status=failed`; fixing the
      cause and re-running completes without duplicates (idempotence).
- [ ] Helper unit tests: settings dedupe, permission grant twice, event register twice, SEO
      conflict error.
