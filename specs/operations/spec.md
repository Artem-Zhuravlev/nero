# SPEC-014 — Operations: Backups, Logs, Mail, Cache, Scheduled Jobs, Retention, Runbooks

**Status:** draft
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-15
**Refines:** SPEC-001 REQ-ARCH-056, 110, 120–122 · Stays free of any hosting product choice.

## Platform facts (verified in `core/upload/`)

1. Cache engines available in `system/library/cache/`: `file` (default), `apc`, `mem`, `memcached`,
   `redis`; session engines: `db` (default), `file`; DB drivers: `mysqli`, `mpdo`, `pgsql`, `mssql`, `mysql`.
2. Mail: `system/library/mail/{mail,smtp}.php`; settings `config_mail_engine`, `config_mail_parameter`,
   `config_mail_smtp_hostname|username|password|port|timeout`, `config_mail_alert`, `config_mail_alert_email`.
3. Admin tools: `tool/backup` (DB tables export/import through the admin UI — loads whole tables in PHP),
   `tool/log` (view/clear error log), `tool/upload`, dashboard widget `extension/dashboard/domovoy`
   (ocStore: clear cache/logs/image cache from the dashboard), `common/developer` (theme/SASS caches),
   `common/security` (move storage). `common/dashboard` warns when `DIR_STORAGE` is the default.
4. Error handling: PHP errors logged to `DIR_LOGS/error.log` via the framework handler (`config error_log`,
   `error_filename`); display controlled by `config_error_display`.
5. There is **no cron/queue system** in core. Scheduled work (currency rates, cleanups) needs an external
   scheduler calling a CLI/HTTP entry. DB sessions (`oc_session`) need an expiry strategy (GC behaviour not verified here; REQ-OPS-041 cleans explicitly); `oc_customer_online`, `oc_cart`, `oc_api_session`, `oc_customer_login` grow unless cleaned.
6. Image cache: `image/cache/` generated on demand and never cleaned by core automatically.

## Problem

A store in production needs recoverability, observability and housekeeping that core does not
provide: tested backups, bounded logs, working mail, known cache behaviour, scheduled
cleanups, data retention, and written runbooks agents and humans can follow.

## Purpose

Specify tooling and policies for backup/restore, logging and monitoring hooks, mail transport, cache and
session configuration, scheduled jobs, data retention/anonymisation, and incident/maintenance
runbooks — independent of where the site is hosted.

## Scope

CLI tools in `scripts/`, job definitions, retention policy, runbooks in `docs/runbooks/`, health endpoint.

## Out of scope

Choosing a host/CDN/monitoring vendor; infrastructure provisioning; CI/CD (deployment specs).

## Functional requirements

### Backups

- **REQ-OPS-001** — `node scripts/backup.mjs [--db] [--files] [--out var/backups/<UTC timestamp>]`
  produces: `db.sql.gz` (mysqldump-compatible dump written by a PHP CLI `scripts/db-dump.php` using the store DB class
  — no dependency on `mysqldump`: streams tables in chunks of 1 000 rows, `DROP/CREATE/INSERT`, transaction-less
  consistent by `LOCK TABLES … READ` per batch group), `files.tar.gz` (`image/catalog`, `DIR_STORAGE/upload`,
  `DIR_STORAGE/download`), `manifest.json` (core version, git commit, build tree hash, row counts per table, sha256 of
  each artefact).
- **REQ-OPS-002** — Restore: `php scripts/db-restore.php <db.sql.gz> [--into-empty]` refuses a non-empty database
  without `--force` and always prints the manifest comparison first; `scripts/restore.mjs` orchestrates DB + files.
- **REQ-OPS-003** — Backup policy (production): daily DB, weekly files, kept 14 daily / 8 weekly / 6 monthly; stored at
  a location other than the web host (transport is deployment-specific); artefacts encrypted at rest with a key not stored
  on the same machine (`age`/GPG documented; step implemented in the deployment spec).
- **REQ-OPS-004** — **A backup counts only after a restore test**: monthly runbook restores the latest backup into a scratch
  database and runs the smoke test against it; the result is appended to `docs/runbooks/restore-log.md`.
- **REQ-OPS-005** — Backups never contain `.env`; DB dumps exclude volatile tables' data
  (`session`, `customer_online`, `api_session`, `cache`-like) but keep their structure.

### Logging and monitoring

- **REQ-OPS-010** — Log files: `error.log` (PHP/framework), plus our channel files `ocs-<ext>.log` via a library
  `Ocs\Logger` (levels debug/info/warn/error, JSON lines: `ts`, `level`, `ext`, `msg`, `ctx` with PII
  scrubbing: e-mail/phone/card patterns masked). Extensions use `Ocs\Logger`, not `file_put_contents`.
- **REQ-OPS-011** — Rotation: `scripts/rotate-logs.mjs` (run by a scheduled job daily) rotates files > 10 MB or
  > 1 day old into `*.log.<date>.gz`, keeps 30 days; never deletes the current log.
- **REQ-OPS-012** — Health endpoint: `index.php?route=extension/ocs_ops/health` (extension `ocs_ops`, GET only) returns JSON
  `{status, db, storage_writable, cache, migrations_pending, disk_free_mb, version, time}` — `200` if all ok, `503`
  otherwise; no secrets; allowed to be called by monitoring only when a bearer token matching
  `config ocs_ops_health_token` is sent (token set per environment via migration with a generated value,
  displayed once). It runs a trivial DB query and a storage write test, < 100 ms.
- **REQ-OPS-013** — Alerting hooks: the error log tail is checked by a scheduled job (`scripts/log-watch.mjs`)
  counting `Fatal|Uncaught|SQL` since last run; above a threshold it sends a mail through the store's mail transport to
  `OPS_ALERT_EMAIL` (.env). Vendor monitoring can replace this later without code changes (health endpoint is the contract).
- **REQ-OPS-014** — Slow-query visibility: with `APP_ENV=local|staging` a debug event
  (`catalog/model/*/before|after`, enabled by setting `ocs_ops_debug`) records query count/time per request into
  `ocs-debug.log`; disabled in production by default.

### Mail

- **REQ-OPS-020** — Mail transport settings are `.env`-driven and applied by migration at deploy: `MAIL_ENGINE`
  (`smtp`|`mail`), `MAIL_SMTP_HOST|PORT|USER|PASS`, `MAIL_FROM`, `MAIL_FROM_NAME`, mapped to `config_mail_*` (+
  `config_email`); the SMTP password is written to `oc_setting` only (SPEC-001 REQ-ARCH-043) and never echoed.
- **REQ-OPS-021** — Local/staging capture mail by pointing SMTP at a local sink (`scripts/mail-sink.mjs`, a minimal SMTP
  receiver writing `.eml` files to `var/mail/`). Replacing core's `Mail` class is not allowed.
- **REQ-OPS-022** — SPF/DKIM/DMARC records are a deployment checklist item (SPEC-009 REQ-QA-051 addendum); bounce
  handling is out of scope.
- **REQ-OPS-023** — Transactional mail templates live in the theme (`mail/*.twig`) and language files; marketing mail is out of scope.

### Cache and sessions

- **REQ-OPS-030** — Cache engine stays `file` (default in `system/config/default.php`, a core file that config alone cannot
  override). Moving to `redis`/`memcached` requires a spec showing the need and, because it means patching core config,
  an OCMOD with an explicit exception to SPEC-007 REQ-OCM-003 recorded in that spec.
- **REQ-OPS-031** — Cache clearing: `php scripts/cache-clear.php [--twig] [--modification] [--images] [--all]` implements
  what `domovoy`/`common/developer` do, safely (keeps `index.html`); `deploy`/migrate invoke `--twig --modification`.
- **REQ-OPS-032** — Sessions stay in DB (`session_engine=db`); the session table cleanup is part of REQ-OPS-040.

### Scheduled jobs

- **REQ-OPS-040** — Jobs are defined in `custom/ops/jobs.json`: `{ id, schedule (cron syntax), command, timeout_s,
  environments, owner }`. A runner `php scripts/jobs-run.php <id>` executes one job with a lock (`GET_LOCK`), timeout,
  structured log line (start/end/duration/exit), and non-overlap. The external scheduler (host cron, task scheduler)
  calls only this runner; `scripts/jobs-crontab.mjs` prints the crontab for an environment.
- **REQ-OPS-041** — Mandatory jobs: `rotate-logs` (daily), `log-watch` (every 5 min, prod), `session-gc` (hourly: delete
  `oc_session` older than 24 h — session lifetime from PHP config — , `oc_api_session` > 1 h), `customer-online-gc`
  (hourly if `config_customer_online`), `cart-gc` (daily: `oc_cart` guest rows older than 30 days), `login-attempts-gc`
  (daily: `oc_customer_login` older than 30 days), `missing-orders-purge` (daily, SPEC-011 REQ-ORD-021: status 0 older than
  30 days), `image-cache-prune` (weekly: files not accessed for 90 days), `seo-redirect-hits` (none), `backup-db` (daily, prod),
  `currency-rates` (hourly, if the owning spec enables it), `sitemap-warm` (daily).
- **REQ-OPS-042** — Jobs are idempotent and resumable; each is covered by a dry-run flag printing counts.

### Retention and privacy

- **REQ-OPS-050** — Retention table (`docs/retention.md`, authoritative for GDPR-style obligations): orders — kept 5 years
  (fiscal), then anonymised; customers inactive > 3 years and without orders — deleted on request or by job;
  `customer_activity`, `customer_search`, `customer_ip`, `customer_login` — 12 months; logs — 30 days; backups — per
  REQ-OPS-003; admin audit — 400 days (SPEC-010).
- **REQ-OPS-051** — Data-subject requests: `php scripts/dsr.php export|anonymise --email <addr>` produces a JSON export
  of the customer's data across core tables and `oc_ocs_*` tables registered via `extension.json` →
  `"personalData": [{"table","column","customerKey"}]`, or anonymises (replaces name/email/phone/address with
  tokens, keeps order totals for accounting). Every extension with PII must declare it (verify check).
- **REQ-OPS-052** — Cookie/consent: the consent mechanism and cookie list are a theme/legal spec; this spec only requires that
  custom code sets no non-essential cookies before consent.

### Runbooks (`docs/runbooks/*.md`)

- **REQ-OPS-060** — Each runbook has: purpose, preconditions, exact commands, verification, rollback. Required: `release`,
  `rollback`, `restore`, `core-upgrade`, `incident-site-down`, `incident-checkout-failing`, `incident-admin-compromise`
  (rotate admin passwords and the API key, kill sessions with `TRUNCATE oc_session`, audit users and the admin audit
  table, restore from a clean backup if tampering is suspected; `config_encryption` is **not** rotated because values
  encrypted with it would become unreadable — the runbook states this), `credentials-rotation`,
  `maintenance-mode` (`config_maintenance` via migration/CLI `scripts/maintenance.php on|off`).
- **REQ-OPS-061** — A release is: `verify` green → backup → build artefact (`build/www` + manifest) → deploy (deployment
  spec) → `migrate up` → `refresh` → `cache-clear` → smoke → monitor 30 min; rollback = previous artefact + DB restore if a
  non-reversible migration ran (migrations declare `reversible: true|false` in a header comment; irreversible ones require
  a fresh backup flag).

## Non-functional requirements

- **REQ-OPS-070** — Backup of a 5 GB catalogue (DB 500 MB) completes in < 15 minutes and uses < 256 MB RAM.
- **REQ-OPS-071** — Jobs run unprivileged (same user as PHP), never as root; write only to `var/` and `DIR_STORAGE`.
- **REQ-OPS-072** — All CLI tools exit non-zero on failure and print a one-line summary parseable by monitoring.

## Data requirements

`oc_ocs_ops_job_run(job_id, started_at, ended_at, exit_code, summary)` (job history, 90 days);
`var/backups/**`, `var/mail/**`, logs in `DIR_LOGS`.

## Edge cases

- Backup during a checkout → table-group locks are short; orders created mid-dump are either entirely in or out per table
  group; restore test tolerates this (row-count drift within tolerance recorded in the manifest).
- Disk full → backup and log rotation abort cleanly with exit code 7 and alert.
- Two schedulers fire the same job → lock prevents overlap.
- Restore into a DB with a different table prefix → refused.
- `config_encryption` lost → encrypted values (e.g. 2FA secrets of SPEC-010) unrecoverable; therefore the setting row is
  included in the backup and the restore runbook verifies it.

## Acceptance criteria

- [ ] Backup → restore into scratch DB → smoke passes; manifest row counts match.
- [ ] Health endpoint returns 503 when DB is unreachable (fixture) and 401 without token.
- [ ] Each mandatory job runs with `--dry-run` and in real mode; overlapping run is refused.
- [ ] `dsr.php anonymise` removes PII for a fixture customer across declared tables and keeps order totals.
- [ ] Runbooks exist with commands verified in `local`.
