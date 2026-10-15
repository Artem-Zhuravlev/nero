# PLAN — SPEC-005 Database and Migrations

## Approach

`scripts/migrate.php` bootstraps minimal core pieces (`DIR_SYSTEM/startup.php` constants from the
generated config, `DB` class) without running the framework. Helpers live in `scripts/lib/php/Migrate/*`
(namespace `Ocs\Migrate`), autoloaded by a tiny local autoloader in the script. `.sql` files are
split on `;` at line end (same approach as the core installer, `install/model/install/install.php`).

## Decisions

| Decision | Choice | Why |
| -------- | ------ | --- |
| Atomicity | none; idempotence instead | MyISAM; MySQL DDL implicit commit |
| Settings writes | per-key upsert helper, not `editSetting` | `editSetting` deletes the whole `code` group |
| Permission grant | direct JSON edit in helper | avoids booting admin models; same result as core `addPermission` |
| OCMOD install | insert `oc_modification` rows ourselves | admin installer is interactive and zip-based |
| Bookkeeping engine | InnoDB | our table, can lock/atomically insert |
| Locking | `GET_LOCK` | prevents double-run on shared DB |
| Language/currency ids | lookup helpers, fail loudly | ids differ per environment |

## Affected files

`scripts/migrate.php`, `scripts/seed.php`, `scripts/refresh.php`, `scripts/lib/php/Migrate/**`,
`custom/db/migrations/0001_baseline.sql` (sanity: creates nothing in core; marks baseline), tests
in `scripts/test/php/`.

## Risks

- Headless OCMOD refresh (REQ-DB-040) is the hardest piece; fallback = faithful port verified
  against admin output on the same inputs.
- Core upgrade may change `oc_setting`/`oc_event` shapes -> reference §9 re-verified on upgrade.
