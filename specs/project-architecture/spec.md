# SPEC-001 — Project Architecture

**Status:** in-progress
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-15

## Problem

OpenCart/ocStore projects rot because customisations are made by editing core files, settings are
clicked together in the admin and lost on redeploy, and nobody knows what differs from the
vanilla distribution. We need a fixed foundation so that (a) the vanilla core can be upgraded or
replaced, (b) any environment can be rebuilt from git, (c) feature specs never relitigate
structure.

## Purpose

Define the stack, repository layout, layering, extension/theme conventions, data-change
mechanism, configuration, security baseline and verification pipeline that every later spec
builds on. Implementation must follow this document literally.

## Scope

1. Base platform and version policy.
2. Repository layout and the **core / custom / build** separation.
3. Build overlay (how `custom/` is composed with `core/` into a docroot).
4. Customisation mechanisms and their order of preference.
5. Extension and theme anatomy and naming.
6. Code layering inside custom PHP code.
7. Database: migrations, seeds, settings-as-code, runtime data.
8. Configuration and secrets, environments.
9. Security baseline.
10. Localisation.
11. Verification pipeline and conventions (commits, specs, traceability).

## Out of scope

Concrete store features (catalogue structure, payment/shipping providers, theme visual design) —
each gets its own spec. Docker, CI, any deployment/hosting (local or production) — each needs its own spec. This spec
is the architecture contract only; nothing is deployed by it.

Companion knowledge documents (normative for understanding the platform, not for rules):
[`docs/ocstore-internals.md`](../../docs/ocstore-internals.md) (how ocStore works) and
[`docs/agent-playbook.md`](../../docs/agent-playbook.md) (how to make changes here).

---

## 1. Platform

- **REQ-ARCH-001** — Base is **ocStore 3.0.3.7** (`VERSION_CORE=ocStore`, build `0002`,
  langpack `UK-EN-RU`), stored unmodified in `core/upload/`. Docs shipped with it
  (`CHANGELOG.md`, `install.txt`, `license.txt`, `README.md`, `SOURCE.txt`, `upgrade.txt`) sit
  next to it in `core/`.
- **REQ-ARCH-002** — Runtime: PHP CLI/SAPI with extensions `mysqli`, `gd`, `curl`, `mbstring`,
  `zip`, `openssl`, `zlib`, `fileinfo`, `exif`, `intl`; MySQL 5.7+/8 or MariaDB 10.4+; web
  server Apache (`mod_rewrite`) or Nginx + PHP-FPM; locally the PHP built-in server.
- **REQ-ARCH-003** — PHP version policy: `system/startup.php` enforces PHP >= 7.3; the code
  base is written for PHP 7.x. This machine has PHP 8.2; therefore `E_DEPRECATED` and `E_NOTICE` are excluded from displayed errors via
  the generated config/ini (not by editing core), and the smoke test (REQ-ARCH-081) is the
  gate that proves the core runs. Production PHP must be a version that passed the smoke test.
- **REQ-ARCH-004** — Dev tooling: Node >= 20 (no npm runtime dependencies in v1 — only Node
  built-ins; scripts are `.mjs`), PHP CLI for database scripts. No Composer in v1.

## 2. Repository layout

```
oc-store-setup/
├── CLAUDE.md                  rules for Claude (read first)
├── README.md                  quick start
├── package.json               npm scripts only, no dependencies
├── .env.example               every config key, no secrets
├── .gitignore
├── core/                      VANILLA ocStore — READ-ONLY
│   ├── upload/                the distribution tree (admin/ catalog/ system/ image/ install/ …)
│   └── CHANGELOG.md install.txt license.txt README.md SOURCE.txt upgrade.txt
├── custom/                    EVERYTHING WE WRITE
│   ├── overlay/               files copied 1:1 onto the docroot (only NEW paths, see REQ-ARCH-012)
│   ├── theme/ocs/             storefront theme  -> catalog/view/theme/ocs/
│   ├── extensions/<ocs_name>/ one directory per extension, each with its own upload/ tree
│   ├── ocmod/*.ocmod.xml      patches to core files (last resort, REQ-ARCH-021)
│   ├── db/
│   │   ├── migrations/NNNN_<slug>.sql    ordered, idempotent
│   │   └── seed/NNNN_<slug>.sql          demo/reference data, idempotent
│   ├── config/
│   │   ├── config.php.tpl            storefront config template
│   │   └── admin-config.php.tpl      admin config template
│   └── i18n/                  translation sources (see section 9)
├── scripts/                   tooling (build.mjs, verify.mjs, smoke.mjs, migrate.php,
│                              install.php, migrate.php, seed.php, php.ini, lib/*.mjs)
├── specs/                     SPEC-NNN directories (this process)
├── docs/                      coding-rules.md, ocstore-internals.md, ocstore-reference.md, agent-playbook.md, architecture-overview.md
├── build/                     GENERATED (git-ignored): build/www = docroot
└── var/                       RUNTIME (git-ignored): storage/, logs/, backups/, db data
```

- **REQ-ARCH-010** — `core/` is read-only: no commit may change any file under `core/` except
  a deliberate, separately-specced core upgrade that replaces the whole tree and records the new
  version in `docs/architecture-overview.md`. `verify` fails if `core/` differs from the
  recorded checksum manifest `core/.manifest.sha256`.
- **REQ-ARCH-011** — All project-authored code, templates, SQL and config templates live under
  `custom/` or `scripts/`. Nothing authored is placed in `build/` or `var/`.
- **REQ-ARCH-012** — `custom/overlay/` may only add paths that **do not exist** in
  `core/upload/`. If a path collides, `build` aborts with the colliding path listed. (Replacing
  a core file is done with OCMOD, never by shadowing it.) Two documented exceptions are
  generated, not overlaid: `config.php` and `admin/config.php`.
- **REQ-ARCH-013** — `build/` and `var/` are disposable and git-ignored; deleting them and
  re-running build + install + migrate must reproduce a working store from git + `.env` alone
  (images and DB content excepted — see REQ-ARCH-052).

## 3. Build overlay

`scripts/build.mjs` produces `build/www/` deterministically:

1. Copy `core/upload/` -> `build/www/` excluding `install/` (REQ-ARCH-032), `config-dist.php`,
   `admin/config-dist.php`.
2. Copy `custom/overlay/` -> `build/www/` (collision check, REQ-ARCH-012).
3. Copy `custom/theme/ocs/` -> `build/www/catalog/view/theme/ocs/`.
4. For each `custom/extensions/<name>/upload/` copy -> `build/www/` (collision check, and
   collisions *between* extensions are also errors).
5. Render `config.php` and `admin/config.php` from the templates using `.env` (REQ-ARCH-040),
   renaming the admin directory if `ADMIN_DIR` != `admin` (REQ-ARCH-033).
6. Write `build/www/.htaccess` from `core/upload/.htaccess.txt`.
7. Write `build/BUILD_INFO.json` (git commit, timestamp, core version, extension list).

- **REQ-ARCH-014** — Build is idempotent and incremental: copies a file only when content
  differs, and **never deletes or overwrites** `image/catalog/` and `image/cache/` (runtime
  uploads and thumbnails). Files that disappeared from sources are removed from `build/www`
  except those two directories.
- **REQ-ARCH-015** — Build fails (non-zero exit) on any collision, missing template key, or
  missing `.env` variable; it never produces a half-valid docroot silently.
- **REQ-ARCH-016** — Directory `image/` runtime content (products, banners) is seeded from
  `custom/seed-images/` only by the `seed` command, never by `build`.

## 4. Customisation mechanisms (order of preference)

Choosing the weakest mechanism that works is mandatory. A pull request/commit that uses a
stronger mechanism must justify it in the spec's `plan.md`.

| # | Mechanism | Use for | Location |
|---|-----------|---------|----------|
| 1 | **Settings** | Anything configurable in admin | migration SQL (REQ-ARCH-050) |
| 2 | **Theme** | Storefront markup/CSS/JS | `custom/theme/ocs/` |
| 3 | **Extension** | New module/payment/shipping/total/feed/dashboard widget | `custom/extensions/ocs_*/` |
| 4 | **Event** | Hooking core controller/model/view/language without patching | event registered by migration, handler in an extension |
| 5 | **OCMOD** | Changing a core file when 1–4 cannot | `custom/ocmod/*.ocmod.xml` |
| 6 | ~~Editing `core/`~~ | **Forbidden** | — |

- **REQ-ARCH-020** — Storefront visual changes go in the theme. `ocs` theme templates override
  by path (`catalog/view/theme/ocs/template/<route>.twig`); anything not overridden falls back
  to `default`. Theme CSS/JS live under `catalog/view/theme/ocs/stylesheet|javascript/`.
- **REQ-ARCH-021** — OCMOD files: one concern per file, named `ocs_<concern>.ocmod.xml`, with
  `<code>`, `<name>`, `<version>`, `<author>`, `<link>`; every `<search>` has `error="skip"`
  only if justified; an OCMOD must be installed through the same migration mechanism (inserted
  into `oc_modification`), then "Refresh modifications" is run by `refresh.php` (REQ-ARCH-054).
- **REQ-ARCH-022** — vQmod is **not** used.
- **REQ-ARCH-023** — Third-party extensions bought/downloaded are vendored as
  `custom/extensions/<vendor_name>/` unchanged, with a `VENDOR.md` (source, version, licence) and
  are subject to the same collision rules. Modifying them is done via OCMOD, not by edit.

## 5. Extensions

```
custom/extensions/ocs_<name>/
├── extension.json             { "code": "ocs_<name>", "type": "module|payment|shipping|total|feed|theme|dashboard|report|captcha", "version": "x.y.z", "requires": [] }
├── upload/
│   ├── admin/controller/extension/<type>/ocs_<name>.php
│   ├── admin/model/extension/<type>/ocs_<name>.php          (only if admin needs DB access)
│   ├── admin/language/<lang>/extension/<type>/ocs_<name>.php   (one per enabled language)
│   ├── admin/view/template/extension/<type>/ocs_<name>.twig
│   ├── catalog/controller/extension/<type>/ocs_<name>.php
│   ├── catalog/model/extension/<type>/ocs_<name>.php
│   ├── catalog/language/<lang>/extension/<type>/ocs_<name>.php
│   ├── catalog/view/theme/default/template/extension/<type>/ocs_<name>.twig
│   └── system/library/ocs/<name>.php                        (domain logic, REQ-ARCH-030)
└── db/                        install.sql / uninstall.sql  (run through migrations, REQ-ARCH-051)
```

- **REQ-ARCH-024** — Naming: extension code `ocs_<snake_name>`; PHP class names follow the
  OpenCart convention derived from the route (`ControllerExtensionModuleOcsFeatured`);
  table names `oc_ocs_<name>_<entity>`; setting keys `<type>_ocs_<name>_<key>`
  (e.g. `module_ocs_featured_status`); events `ocs_<name>_<purpose>`.
- **REQ-ARCH-025** — Every extension ships **all** enabled languages (REQ-ARCH-090) or the
  verify step fails. English (`en-gb`) is mandatory.
- **REQ-ARCH-026** — Permissions: an extension's install migration grants `access` and `modify`
  on its routes to the Administrator user group (`oc_user_group`), via a documented helper in
  `scripts/lib/sql-helpers.sql`.
- **REQ-ARCH-027** — An extension must be removable: its `db/uninstall.sql` drops only objects it
  created, in reverse order. Uninstall never touches core tables except deleting its own rows.

## 6. Code layering (custom PHP)

OpenCart's MVC-L is kept; the rules below add discipline on top.

```
Controller (HTTP <-> domain translation only)
   └── Model (OpenCart DB access via $this->db, one model per aggregate)
   └── Library  system/library/ocs/*  (domain logic, no $_GET/$_POST/header(), no output)
View (Twig; no logic beyond loops/ifs/format filters)
```

- **REQ-ARCH-030** — Business rules (pricing, eligibility, calculations, formatting of domain
  values) live in `system/library/ocs/*.php` classes, loaded with
  `$this->load->library('ocs/<name>')` (class `Ocs\<Name>`, file
  `system/library/ocs/<name>.php`). Libraries take dependencies (`$registry`/`$db`) in the
  constructor and never read superglobals.
- **REQ-ARCH-031** — Controllers contain no SQL and no business rules. Models contain SQL only
  (parameterised via `$this->db->escape()` or `(int)` casts; no string-concatenated user input).
  Twig templates contain no business rules and no SQL.
- **REQ-ARCH-034** — Every PHP file starts with `<?php` and, in `system/library/ocs/`, with
  `namespace Ocs;`. All user-facing strings go through language files (no hard-coded text in
  controllers/templates).
- **REQ-ARCH-035** — No global state, no `$GLOBALS`, no `define()` outside generated config, no
  `eval`, no `@` error suppression, no `die()` outside install scripts.
- **REQ-ARCH-032** — The `install/` directory is **never** present in `build/www`. The stock installers must not be run
  against `core/`: `install/cli_install.php` derives `DIR_OPENCART` from its own location and **writes `config.php` and
  `admin/config.php` into it and checks storage directories under it**, which would modify the read-only core
  (verified in `core/upload/install/cli_install.php`, `write_config_files()`). Installation is therefore done by our own
  `scripts/install.php`, which replays the installer's database steps against the target DB using `core/upload/install/opencart.sql`
  as data: table-prefix replace on `DROP/CREATE/INSERT` statements, `SET sql_mode=''`, admin user row (id 1, group 1, salted-SHA1
  hash as in reference §2), `config_email`, 1024-char `config_encryption`, default API user + `config_api_id`,
  `config_invoice_prefix`, `product.viewed=0`. Configs are produced by the build (SPEC-003), never by an installer. A test compares
  the resulting row set with a run of the stock installer executed on a throw-away copy of `core/upload/` in `var/` (so differences
  after a core upgrade are detected).
- **REQ-ARCH-033** — Admin directory name is configurable (`ADMIN_DIR`, default `admin`;
  production should differ). The build renames the directory and rewrites `HTTP_*`/`DIR_*`
  constants in generated configs accordingly.

## 7. Configuration and environments

`.env` (git-ignored) is the single source of environment configuration; `.env.example` is
committed and lists every key:

| Key | Meaning | Local default |
|-----|---------|---------------|
| `APP_ENV` | `local` \| `staging` \| `production` | `local` |
| `APP_URL` | public base URL with trailing slash | `http://localhost:8080/` |
| `ADMIN_DIR` | admin directory name | `admin` |
| `DB_HOST` `DB_PORT` `DB_NAME` `DB_USER` `DB_PASS` `DB_PREFIX` | database | `127.0.0.1` `3306` `ocstore` `ocstore` — `oc_` |
| `STORAGE_DIR` | absolute path to storage **outside docroot** | `<repo>/var/storage/` |
| `ADMIN_USER` `ADMIN_EMAIL` `ADMIN_PASS` | initial admin (install only) | `admin` / — / generated |
| `PHP_BIN` | PHP executable | `php` |
| `PORT` | local server port | `8080` |

- **REQ-ARCH-040** — Core ships `config-dist.php` and `admin/config-dist.php` as **empty (0 byte)
  files**; the stock installer writes `config.php` files. We therefore author the templates
  ourselves. The two OpenCart config files are *generated* from
  `custom/config/*.tpl` by `build`; templates contain `{{KEY}}` placeholders only. They are never
  committed (`build/` ignored), and `config-dist.php` is never used.
- **REQ-ARCH-041** — `DIR_STORAGE` points outside the docroot (`STORAGE_DIR`); the build copies
  `core/upload/system/storage/` skeleton there on first run and never overwrites existing files.
- **REQ-ARCH-042** — `DIR_CACHE`, `DIR_LOGS`, `DIR_SESSION`, `DIR_UPLOAD`, `DIR_DOWNLOAD`,
  `DIR_MODIFICATION`, `DIR_IMAGE` are set explicitly by the template relative to `DIR_STORAGE`
  (image stays in the docroot).
- **REQ-ARCH-043** — Secrets (`DB_PASS`, `ADMIN_PASS`, API keys) exist only in `.env` and in the
  DB; they are never written to docs, specs, commit messages, logs or `BUILD_INFO.json`.
  Verify greps tracked files for the values in `.env` and fails on a hit.
- **REQ-ARCH-044** — Differences between environments are expressed **only** through `.env`
  values; there are no `if (APP_ENV == …)` branches in custom code except in
  `scripts/` and the config templates (error display on/off, `config_error_display`).

## 8. Database

- **REQ-ARCH-050** — **Settings as code.** Anything that can be configured in admin (store name,
  languages, currencies, geo zones, tax classes, order statuses, shipping/payment extension
  settings, layouts, theme selection, SEO URL settings) is changed by a migration that uses
  `INSERT … ON DUPLICATE KEY UPDATE` / guarded `UPDATE` on `oc_setting`, `oc_currency`, … so
  re-running is safe. Manual admin edits are allowed in `local` for experiments but are not
  considered part of the store until captured in a migration.
- **REQ-ARCH-051** — **Migrations.** Files `custom/db/migrations/NNNN_<slug>.sql`, four-digit
  zero-padded, applied in order by `scripts/migrate.php`, recorded in table
  `oc_ocs_migration (id VARCHAR(64) PK, applied_at DATETIME, checksum CHAR(64))`. An applied
  migration file must never be edited (checksum mismatch aborts `migrate`); fixes are new
  migrations. Core tables are almost all MyISAM (157 of 159 in `install/opencart.sql`), so there are no atomic migrations: each migration is idempotent and re-runnable (SPEC-005), uses `{{PREFIX}}` for the
  table prefix, and is idempotent (`CREATE TABLE IF NOT EXISTS`, guarded `ALTER`).
- **REQ-ARCH-052** — **Runtime data** (products, categories created in admin, customers, orders,
  uploaded images) is *not* in git. Reference/demo data needed for development lives in
  `custom/db/seed/NNNN_<slug>.sql` + `custom/seed-images/`, applied only by `seed`, and is
  idempotent. Production never runs `seed`.
- **REQ-ARCH-053** — Core tables are never altered structurally. Extra columns/tables are added
  as separate `oc_ocs_*` tables joined by key. Core table rows may only be inserted/updated
  via settings (REQ-ARCH-050) or documented extension install steps.
- **REQ-ARCH-054** — After every `migrate`, the update procedure runs the "refresh modifications" and "clear
  cache" equivalents through CLI (`scripts/refresh.php`) so OCMOD and events take effect.
- **REQ-ARCH-055** — Charset: tables stay as created by core (`utf8`, `utf8_general_ci`);
  custom tables use `ENGINE=InnoDB DEFAULT CHARSET=utf8` (core is MyISAM; custom tables never rely on foreign keys to core tables, only on joins by key).
- **REQ-ARCH-056** — `scripts/backup.mjs` dumps the DB and tars `image/catalog` into
  `var/backups/<timestamp>/` and is run automatically before any `migrate` when
  `APP_ENV != local`.

## 9. Localisation

- **REQ-ARCH-090** — Enabled store languages are declared in `custom/i18n/languages.json` and
  mirrored into the DB by a migration (SPEC-008). The distribution ships only `uk-ua` and `en-gb`
  (no `ru-ru`, despite the "UK-EN-RU" label); `uk-ua` is also the hard-coded runtime fallback.
- **REQ-ARCH-091** — Language strings of custom code live in each extension's/theme's own
  language files. Overriding core strings uses OpenCart's per-route language override file added
  as a **new** path (e.g. `catalog/language/<lang>/ocs/override.php` loaded by an event), not by
  editing core language files.
- **REQ-ARCH-092** — Verify compares keys across languages for every custom language file and
  fails on a missing key.

## 10. Security baseline

- **REQ-ARCH-060** — Docroot (`build/www`) contains no `.env`, no `var/`, no `custom/`, no
  `specs/`, no `scripts/`, no SQL dumps, no storage. The web server serves only `build/www`.
- **REQ-ARCH-061** — Generated `.htaccess` deny
  `*.twig`, `*.tpl`, `*.ini`, `*.log`, `*.sql`, `.git*`, `config.php` over HTTP; any other server setup (nginx, built-in server) must reproduce the same deny-list, which the
  smoke test verifies (SPEC-009 REQ-QA-060).
- **REQ-ARCH-062** — Production requires: HTTPS (`APP_URL` https), `ADMIN_DIR` != `admin`,
  `config_error_display = 0`, `config_error_log = 1`, strong DB password, DB user limited to its
  schema. `verify --env production` fails otherwise.
- **REQ-ARCH-063** — All custom SQL uses escaped/cast input; this core runs Twig with
  `autoescape => false`, so custom code must escape explicitly: user-controlled strings are
  escaped in the controller (`htmlspecialchars($v, ENT_QUOTES, 'UTF-8')`) or with `|escape` in
  the template; unescaped output is allowed only for admin-authored HTML
  (`html_entity_decode`), with a comment.
- **REQ-ARCH-064** — Custom admin controllers check `user->hasPermission('modify', $route)`
  before every state-changing action, and use the standard `user_token` check.
- **REQ-ARCH-065** — File uploads in custom code reuse core's `tool/upload` checks; no new
  free-form upload endpoints.

## 11. Verification and conventions

- **REQ-ARCH-080** — `npm run verify` runs, fail-fast, in this order:
  1. `core/` checksum manifest matches (REQ-ARCH-010)
  2. layering rules (REQ-ARCH-031/034/035) via `scripts/lib/lint-layers.mjs` (pattern checks)
  3. `php -l` on every `custom/**/*.php` and `scripts/**/*.php`
  4. migrations: filenames sequential, no gaps/duplicates, no edit of applied (checksum file
     `custom/db/migrations/.checksums`)
  5. i18n key parity (REQ-ARCH-092)
  6. `build` into a temp dir (collision check)
  7. spec traceability: every `REQ-*` in specs with status >= `implemented` has a task and a
     check id; every check id in code/scripts maps to an existing REQ
- **REQ-ARCH-081** — Smoke test (`scripts/smoke.mjs`, run by the verify step and
  `verify --smoke`): with the store running, `GET /` -> 200 and contains the store name;
  `GET /index.php?route=common/home` -> 200; `GET /<admin>/` -> 200 login form;
  `GET /.env`, `/config.php`, `/system/storage/` -> 403/404; PHP error log has no new fatal.
- **REQ-ARCH-082** — Commits: `type(scope): message [SPEC-NNN]`, directly on `main` (solo
  project), no force-push. One spec per commit series; never mix a core upgrade with feature
  work.
- **REQ-ARCH-084** — Agent context: `CLAUDE.md` must instruct every agent to read
  `docs/ocstore-internals.md` and `docs/agent-playbook.md` before changing code. Any spec that
  teaches something new about the platform updates those documents in the same commit.
- **REQ-ARCH-083** — Coding rules in `docs/coding-rules.md` are binding; spec-level exceptions
  are written in the spec's `plan.md`.

## 12. Core upgrade and patching policy

- **REQ-ARCH-100** — Core upgrades are their own spec (`SPEC-NNN core-upgrade-<version>`):
  replace `core/upload/` wholesale from the official ocStore release, regenerate
  `core/.manifest.sha256`, update the version line in `docs/architecture-overview.md`, then run
  verify. Never mix with feature work (REQ-ARCH-082).
- **REQ-ARCH-101** — Every OCMOD, event and overlay file that depends on a specific core file
  records it in a header comment (`@core-file path`, `@core-version 3.0.3.7`). Verify
  extracts these and, on a core upgrade, lists every dependent whose core file hash changed, so
  they are re-reviewed.
- **REQ-ARCH-102** — Security hotfixes to core are applied as OCMOD (`ocs_hotfix_<id>.ocmod.xml`)
  with a link to the advisory, and removed when the core upgrade includes the fix.

## 13. Caching and performance

- **REQ-ARCH-110** — Use the framework cache (`$this->cache->get|set|delete`, file engine
  by default, `cache_expire` 3600 s) for expensive custom queries. Key format
  `ocs_<ext>.<entity>.<id|hash>[.<language_id>.<store_id>]`; the cache entry is deleted by the
  code that mutates the entity (admin model `edit/delete`).
- **REQ-ARCH-111** — No query inside a loop (N+1) in custom models: batch with `IN (...)` or
  joins. Custom queries on large tables must use existing indexes or ship an index in an
  `oc_ocs_*` table / migration (core tables are not altered, REQ-ARCH-053).
- **REQ-ARCH-112** — Twig template cache stays enabled in non-local environments
  (`developer_theme` off); theme/SCSS developer flags are allowed only when `APP_ENV=local`.
- **REQ-ARCH-113** — Images are served through core's resize model (`tool/image`, cache in
  `image/cache/`); custom code never hot-links original uploads into listings and never
  stores generated thumbnails elsewhere.
- **REQ-ARCH-114** — Static assets of the theme are versioned by a query string derived from
  `BUILD_INFO.json` commit hash so browsers refresh them after each build.

## 14. Logging and error handling

- **REQ-ARCH-120** — Errors go to `DIR_LOGS/error.log` via the framework handler; custom
  diagnostic logging uses `$this->log->write()` with a prefix `[ocs_<ext>]`. Custom code logs
  no secrets, no card data, no full customer records, no raw request bodies.
- **REQ-ARCH-121** — Custom code does not catch-and-swallow: exceptions are either handled with
  a user-visible message from a language file or rethrown. Controllers return the standard
  `error/not_found` route for unknown ids instead of rendering empty pages.
- **REQ-ARCH-122** — `config_error_display` is `0` outside `local`; verify (production profile)
  fails otherwise. Log rotation/size is an operations concern (own spec).

## 15. SEO, URLs and routing

- **REQ-ARCH-130** — SEO URLs stay enabled (`config_seo_url=1`; SeoPro `config_seo_pro` decision
  is made once in the store-config spec and recorded here). Custom pages get slugs through
  `oc_seo_url` rows created in migrations, one per enabled language and store, with keywords
  unique per `(store_id, language_id)`.
- **REQ-ARCH-131** — Custom routes live under `extension/ocs_<name>/…` (storefront) and the same
  in admin; custom code never adds routes outside this namespace and never reuses a core
  route name.
- **REQ-ARCH-132** — URLs are built only with `$this->url->link($route, $args)`; no
  hand-concatenated URLs, so SEO rewriting and multi-store domains keep working.
- **REQ-ARCH-133** — Canonical, `hreflang` and meta tags are set through `$this->document`
  (`setTitle`, `setDescription`, `addLink`), never hard-coded in templates.

## 16. Multi-language, multi-currency, multi-store

- **REQ-ARCH-140** — Any content table added by us that holds translatable text follows the
  core pattern: `oc_ocs_<entity>` + `oc_ocs_<entity>_description(<id>, language_id, …)`.
- **REQ-ARCH-141** — Prices are read through `$this->currency->format()` /
  `$this->tax->calculate()`; custom code never formats currency or computes tax by hand.
- **REQ-ARCH-142** — Anything store-specific (settings, layouts, slugs) carries `store_id`; code
  reads settings with `$this->config->get()` (already store-scoped by `startup/startup`), never
  by direct `oc_setting` queries except in migrations.

## 17. Testing strategy

- **REQ-ARCH-150** — Three levels, all runnable without Docker:
  1. *Static*: `php -l`, layering lint, i18n parity, migration lint (REQ-ARCH-080).
  2. *Unit*: libraries in `system/library/ocs/` are pure PHP and must be testable without
     bootstrapping the framework by passing a stub registry; tests live in
     `custom/extensions/<ext>/tests/` and run with a minimal in-repo PHP runner
     (`scripts/run-php-tests.php`, no Composer dependency in v1).
  3. *Smoke/HTTP*: `scripts/smoke.mjs` against a running build (REQ-ARCH-081), extended per
     feature spec with its acceptance paths.
- **REQ-ARCH-151** — Each feature spec lists its checks (`TEST-<AREA>-NNN`) and states the
  level of each. A requirement with no check is a spec defect.
- **REQ-ARCH-152** — Tests never use production data and never write outside `var/`.

## 18. Documentation upkeep

- **REQ-ARCH-160** — `docs/ocstore-internals.md` is corrected whenever a spec or an
  investigation shows it to be wrong; items marked `[std]` are promoted to verified (with the
  core file reference) the first time they are exercised.
- **REQ-ARCH-161** — `docs/agent-playbook.md` gains a recipe whenever a new kind of change is
  done twice.
- **REQ-ARCH-162** — Decisions that deviate from this spec are recorded in the relevant
  `plan.md` and, if lasting, folded back into this spec via the STOP -> UPDATE -> RE-APPROVE
  loop.

## Open questions

- Serving/deployment (web server, installer invocation, hosting) is not specified: a deployment spec must define it before anything runs. Owner: Artem Zhuravlev. Needed before the first task that requires a running store (smoke, L2/L3 tests).
- Final language set, store niche, payment/delivery providers: store-config spec (not written yet).

## Data requirements

Only `oc_ocs_migration` is introduced by this spec (REQ-ARCH-051). Everything else is core.

## Edge cases

- Overlay path collides with core -> build aborts, lists path (REQ-ARCH-012).
- `.env` missing a key -> build aborts naming the key (REQ-ARCH-015).
- Applied migration edited -> migrate aborts with checksum mismatch (REQ-ARCH-051).
- `image/catalog` exists from earlier runs -> never touched by build (REQ-ARCH-014).
- DB unreachable -> the install procedure stops before migrating with a clear message, build output kept.
- Core upgraded -> new manifest, rerun verify + smoke; any OCMOD `<search>` that no longer
  matches shows up in the OCMOD log and fails `refresh` (REQ-ARCH-054).

## Acceptance criteria

- [ ] `core/` unchanged vs manifest; `npm run verify` green on a clean checkout + `.env`.
- [ ] `build/www` is produced from `core/` + `custom/` only and contains no `install/`.
- [ ] Deleting `build/` and `var/storage` then running build + install + migrate yields a working store.
- [ ] Smoke test passes (home, admin login, denied sensitive paths).
- [ ] A deliberately colliding overlay file makes `build` fail (negative test).
- [ ] A deliberately edited applied migration makes `migrate` fail (negative test).
