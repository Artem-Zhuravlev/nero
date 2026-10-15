# SPEC-009 — Quality Gates, Lint Rules and Security Checklist

**Status:** in-progress
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-15
**Refines:** SPEC-001 sections 10, 11, 17

## Problem

SPEC-001 lists rules (no SQL in controllers, no `@`, escape output…) and a verify order, but an
implementer needs the exact checks, patterns, severities, exemptions and outputs, otherwise the
rules are advisory.

## Purpose

Define `npm run verify` precisely: each check's id, what it scans, pattern/logic, severity, how to
exempt, output format, exit codes; plus the manual review checklist and the security checklist.

## Scope

`scripts/verify.mjs`, `scripts/lib/check-*.mjs`, check ids, exemptions file, reports, review
checklists. Checks that belong to other specs are *invoked* here and *defined* there.

## Out of scope

CI hosting, pre-commit hooks installation (may be added later; the same commands are used).

## Functional requirements

### Runner

- **REQ-QA-001** — `node scripts/verify.mjs [--only <check-id,…>] [--env local|staging|production] [--smoke] [--json]`.
  Runs checks in the order of the table in REQ-QA-010, stops at the first **error** unless
  `--keep-going`, prints `PASS|WARN|FAIL <id> <duration>` per check and a summary; exit 0 only
  with no FAIL.
- **REQ-QA-002** — Severity: `error` fails the run, `warn` is printed and counted; warnings
  reaching 20 fail the run (`warn-budget`) to prevent erosion.
- **REQ-QA-003** — Finding format: `<severity> <check-id> <path>:<line> <message> [REQ-…]`; `--json`
  emits an array of the same fields. Paths are repo-relative with `/`.
- **REQ-QA-004** — Exemptions: `verify.exemptions.json` entries `{ "check": id, "path": glob, "line"?: n,
  "reason": text, "owner": name, "expires": "YYYY-MM-DD" }`. Expired or reason-less entries
  are errors. Inline exemption comment `// verify-ignore <check-id>: <reason>` applies to the next
  line only. Core and vendored extensions are exempt from lint checks by default (not from
  integrity checks).

### Check table (order)

- **REQ-QA-010** —

  | Id | Check | Defined in | Severity |
  |----|-------|------------|----------|
  | `core-manifest` | `core/` matches `core/.manifest.sha256` | SPEC-001 REQ-ARCH-010 | error |
  | `layout` | required top-level dirs/files exist; no stray files at extension roots | SPEC-001 §2, SPEC-004 | error |
  | `php-lint` | `php -l` on `custom/**/*.php`, `scripts/**/*.php` | SPEC-001 | error |
  | `layers` | layering lint (REQ-QA-020…029) | this spec | error |
  | `security-lint` | REQ-QA-030…036 | this spec | error |
  | `extensions` | manifest + declarations vs migrations/files | SPEC-004 | error |
  | `migrations` | numbering, forbidden statements, prefix, shape | SPEC-005 | error |
  | `events-ocmod` | trigger grammar, signatures, registry doc, OCMOD apply + `php -l` | SPEC-007 | error |
  | `theme` | no identical copies, `@core-file` headers, hooks doc present | SPEC-006 | error |
  | `i18n` | language parity | SPEC-008 | error |
  | `build-check` | `build.mjs --check` (collisions, templates) | SPEC-003 | error |
  | `traceability` | REQ ⇄ task ⇄ check ids (REQ-QA-040…044) | this spec | error |
  | `secrets` | no secret values from `.env` in tracked files | SPEC-001 REQ-ARCH-043 | error |
  | `prod-profile` | production settings (only with `--env production`) | SPEC-001 REQ-ARCH-062 | error |
  | `smoke` | HTTP smoke (only with `--smoke`) | SPEC-001 REQ-ARCH-081 | error |

### Layering lint (`layers`)

Scope: `custom/**/{controller,model,library,view,template}` PHP and Twig, case-insensitive on
paths. Implementation uses a small PHP tokenizer-based scanner (`php -r` with `token_get_all`)
for PHP rules to avoid false positives in strings/comments, and regex for Twig.

- **REQ-QA-020** — *No SQL outside models*: in files under `…/controller/`, `…/library/ocs/`, and
  event handler files, the tokens `$this->db`, `->query(`, `DB_PREFIX` are forbidden. In `…/model/`
  they are allowed. In Twig: any of `SELECT `, `INSERT `, `UPDATE `, `DELETE ` followed by SQL
  keywords -> error.
- **REQ-QA-021** — *SQL value safety (models)*: any string passed to `->query(` that contains a `$`
  variable must wrap each variable in `(int)`, `(float)`, `$this->db->escape(` or a known-safe
  numeric expression; checked token-wise. Heuristic misses are covered by review (REQ-QA-050).
- **REQ-QA-022** — *Superglobals*: `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, `$_SESSION`, `$_FILES`,
  `$_SERVER` forbidden in `custom/` (use `$this->request->*` / `$this->session`); allowed in
  `scripts/`.
- **REQ-QA-023** — *Forbidden constructs*: `eval(`, backtick execution, `@` error-suppression operator,
  `die(`/`exit(` (outside `scripts/`), `extract(`, `$$` variable-variables, `create_function`,
  `unserialize(` on non-literal input, `shell_exec|exec|system|passthru|proc_open|popen`
  in `custom/` (allowed in `scripts/`), `global ` keyword, `$GLOBALS`.
- **REQ-QA-024** — *Library purity*: files in `system/library/ocs/` must declare `namespace Ocs…;`,
  not reference `$this->request|response|session|document|load`, and not echo/print
  (constructor receives `$registry`; it may use `$registry->get('db'|'config'|'cache'|'log'|'language')` only).
- **REQ-QA-025** — *Controller size*: methods > 80 lines or files > 400 lines -> warn (signals
  logic that belongs in a library).
- **REQ-QA-026** — *No magic numbers*: numeric literals other than `-1,0,1,2,100` in conditions or
  arithmetic inside `…/library/ocs/` -> warn unless assigned to a `const`/named variable.
- **REQ-QA-027** — *Language strings*: in Twig, text nodes containing letters outside Twig tags,
  HTML attributes of `title|alt|placeholder|aria-label` with literal letters, and PHP string
  literals passed to `$data['text_…']`/`$this->response->setOutput` that look like prose
  (>= 2 words with letters) -> error; whitelist for HTML entities, numbers, symbols.
- **REQ-QA-028** — *Route namespace*: custom controllers must live under `extension/ocs_*` or the
  theme extension route (SPEC-001 REQ-ARCH-131); a controller file outside `extension/` in
  `custom/` -> error.
- **REQ-QA-029** — *URL building*: string literals containing `index.php?route=` or starting with
  `http://|https://` inside controllers/templates -> error (use `$this->url->link`); external
  links to documented third-party domains allowed via exemption.

### Security lint (`security-lint`)

- **REQ-QA-030** — *Twig escaping*: in `custom/**/*.twig`, a `{{ expr }}` printing a variable whose
  name matches `(name|title|description|comment|text|message|address|email|phone|query|keyword|search|
  filter|input|url|href|src)` without `|escape`, `|e`, `|raw` + preceding `{# safe: … #}` comment, or
  a documented numeric/date filter -> error. Language-string variables `text_*`, `button_*`,
  `entry_*`, `error_*`, `heading_title`, `help_*`, `tab_*`, `column_*` are treated as safe
  (translators' trusted text); user-originating data is not.
- **REQ-QA-031** — *Admin write actions*: in `admin/controller/**` of custom extensions, every method
  that reads `$this->request->post` or `server['REQUEST_METHOD'] == 'POST'` must call
  `hasPermission('modify'` in the same file (via `validate()`).
- **REQ-QA-032** — *Admin URLs*: every `$this->url->link(` in admin controllers includes
  `user_token` in its second argument.
- **REQ-QA-033** — *File uploads*: `move_uploaded_file`, `$this->request->files`, `copy(` with
  `http` sources forbidden in `custom/` (reuse `tool/upload`, REQ-ARCH-065).
- **REQ-QA-034** — *Outbound HTTP*: `curl_init`, `file_get_contents('http`, `fopen('http` allowed
  only in files under `…/library/ocs/` named `*client*.php`, with a timeout set
  (`CURLOPT_TIMEOUT`) and TLS verification not disabled (`CURLOPT_SSL_VERIFYPEER=false` ->
  error).
- **REQ-QA-035** — *Crypto & passwords*: `md5(`/`sha1(` for passwords/tokens, `rand(`/`mt_rand(`
  for tokens -> error (use `random_bytes`/`password_hash`); `md5` allowed for cache keys with
  comment.
- **REQ-QA-036** — *Logging secrets*: `->log->write(` arguments referencing variables named
  `*password*|*token*|*secret*|*card*|*cvv*|*key*` -> error.

### Traceability

- **REQ-QA-040** — Every `REQ-<AREA>-<NNN>` defined in a spec with status >= `approved` appears in
  its `tasks.md` (as `REQ-AREA-NNN` or in a range `NNN..MMM`/list) -> else error.
- **REQ-QA-041** — Every `TEST-<AREA>-<NNN>` referenced in `tasks.md` exists as a check: a test
  file with `TEST-… — verifies REQ-…` marker, or a documented manual/review item marked
  `review`/`manual` in the table. Missing -> error for specs with status >= `implemented`.
- **REQ-QA-042** — Every `REQ-` id mentioned in code comments or tests exists in some spec; unknown -> error.
- **REQ-QA-043** — Spec status coherence: `implemented|verified` requires all tasks `done`; `approved`
  specs may have `pending` tasks; a `draft` spec must not have code referencing its ids.
- **REQ-QA-044** — `specs/README.md` table lists every spec directory exactly once with the status
  from its `spec.md` header.

### Review checklists (manual, recorded in PR/commit notes or `specs/<x>/review.md`)

- **REQ-QA-050** — *Code review checklist* for each change: spec/REQ ids cited; mechanism order
  respected (SPEC-001 §4); no core edits; SQL values safe; output escaped; permissions and
  `user_token` in admin; language files complete; migration idempotent and re-run-safe; settings
  keys per convention; cache invalidation on mutation; N+1 avoided; logs without secrets;
  docs updated (internals/reference/playbook) when platform knowledge changed.
- **REQ-QA-051** — *Security checklist* for releases: HTTPS; `ADMIN_DIR` renamed; storage outside
  docroot; `config_error_display=0`; DB user least-privilege; default API key rotated/disabled
  (`oc_api` default user created by installer, reference §2); admin password strong; unused
  core extensions (payment/shipping/modules shipped but unneeded) uninstalled and their admin
  routes removed from user-group permissions; file-permission audit (docroot not writable by PHP
  except `image/` and `DIR_STORAGE`); `.htaccess`/nginx deny rules verified by smoke; backups
  tested by restore.
- **REQ-QA-052** — *Release checklist*: `verify` green with `--env production --smoke`, backup taken,
  `migrate status` clean, `refresh` done, rollback plan (previous build + DB backup) noted.

### Smoke details

- **REQ-QA-060** — `smoke` requests, with expected result: `/` 200 body contains `<title>` and the store
  name setting; `/index.php?route=common/home` 200; `/index.php?route=product/category&path=<first category>`
  200 if any; `/index.php?route=information/contact` 200; `/index.php?route=checkout/cart` 200;
  `/<ADMIN_DIR>/` 200 contains a login form; `/.env` `/config.php` `/<ADMIN_DIR>/config.php`
  `/system/storage/logs/error.log` `/custom/` `/specs/` `/var/` -> 403 or 404; response headers
  of `/` include `Content-Type: text/html; charset=utf-8`; no `Fatal error|Parse error|Warning:` in any
  body; and `DIR_LOGS/error.log` has no new lines containing `Fatal` or `Uncaught` during the run.
- **REQ-QA-061** — Smoke accepts `--base-url`; it never writes to the DB except through normal
  page views (sessions, `oc_customer_online`); it does not place orders.

## Non-functional requirements

- **REQ-QA-070** — Full verify without smoke completes in < 30 s on this repo size (excluding build
  copy of core, which `--check` avoids).
- **REQ-QA-071** — Checks are deterministic and independent; running `--only` any subset gives
  the same findings for that subset.
- **REQ-QA-072** — The PHP scanner is a single dependency-free script `scripts/lib/php/scan.php` used by
  all PHP-aware rules; Twig/XML/SQL/JSON checks are Node.

## Data requirements

`verify.exemptions.json` (committed); no runtime data.

## Edge cases

- Third-party vendored extension fails lint -> exempt by path, but integrity checks stay.
- A migration `.sql` containing `SELECT` in a string -> `layers` does not scan migrations (SPEC-005 owns).
- Generated `docs/core-touchpoints.md` is excluded from traceability scanning.
- Windows CRLF files: line numbers still reported; format checks normalise before hashing but a
  repo-wide `.gitattributes` (`* text=auto eol=lf` except `core/**` binary-safe) is added to avoid it.

## Acceptance criteria

- [ ] Every rule has a fixture pair (violating/clean) under `scripts/test/fixtures/qa/` and a test
      asserting the exact finding.
- [ ] `verify` on an empty `custom/` passes; adding a controller with `$this->db` fails with REQ-QA-020.
- [ ] Exemption expiry and missing reason are rejected.
- [ ] `--json` output validates against a documented schema.
