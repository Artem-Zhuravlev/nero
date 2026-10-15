# SPEC-022 — Multi-store and Environment Profiles

**Status:** draft
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-15
**Refines:** SPEC-001 section 7, 16 · SPEC-003 · SPEC-005 · SPEC-013

## Platform facts (verified in `core/upload/`)

1. A **store** is a row in `oc_store(store_id, name, url, ssl)`; store 0 is the default (implicit). `catalog/controller/startup/startup.php` resolves the store by comparing the request host+path
   to `url` (http) or `ssl` (https) with `www.` removed; `?store_id=` in GET overrides; unknown hosts fall back to store 0 with `config_url = HTTP_SERVER`.
2. Settings load as `store_id = 0 OR store_id = current` ordered by store id, so a store's rows override store 0's.
3. Entities are linked to stores through `*_to_store` tables (product, category, information, manufacturer, article, blog_category); layouts through `oc_layout_route.store_id`; SEO slugs through
   `oc_seo_url.store_id`; customers have `store_id`; orders store `store_id`, `store_name`, `store_url`.
4. Theme per store via `config_theme` store override and `theme_<name>_*` settings saved per `store_id` (`editSetting('theme_default', …, $store_id)`).
5. Currency, language lists are global (not per store); `config_language`/`config_currency` defaults are per-store settings.
6. Generated `config.php` has one `HTTP_SERVER`/`HTTPS_SERVER`; the same codebase serves all stores; the `modification` cache and `DIR_CACHE` are shared.

## Problem

The store may later add a second storefront (another domain, B2B shop, language-specific domain) or must run in several environments (local, staging, production) with consistent code and different
configuration. Without rules, store-specific logic leaks into code and environment differences become undocumented drift.

## Purpose

Specify how stores are modelled as data, how code stays store-agnostic, and what exactly may differ between environments and how that is enforced.

## Scope

Store definitions as code, store-scoped settings/content/SEO/layout/theme, per-store behaviour in extensions, environment profiles and their allowed differences, configuration drift detection, domain/HTTPS handling.

## Out of scope

Separate databases per store, white-label SaaS tenancy, store-specific codebases.

## Functional requirements

### Stores as data

- **REQ-STO-001** — Stores are declared in `custom/stores/stores.json`: `{ "code": "main", "id": 0, "name": …, "url": "https://example.com/", "ssl": "https://example.com/", "theme": "ocs", "language": "uk-ua",
  "currency": "UAH", "layoutProfile": "default" }`. Store 0 is `main`. The URLs are templates with `{{APP_URL}}` for the main store and `{{STORE_<CODE>_URL}}` for others, resolved from `.env`.
- **REQ-STO-002** — A migration (`NNNN_stores`) makes `oc_store` match the file (insert missing with the **declared** `store_id` using explicit id insert; update name/url/ssl) and writes the store-scoped
  settings (`config_name`, `config_url`, `config_ssl`, `config_theme`, `config_language`, `config_currency`, `config_meta_*`, `theme_*`) through the setting helper with the store id (SPEC-005 REQ-DB-020).
  Stores are never deleted by migrations (`status` via `config_store_status`-like setting is not core; removal is manual with a runbook).
- **REQ-STO-003** — Code never hard-codes a store id. It reads `$this->config->get('config_store_id')`; library code receives the store id as an argument.
- **REQ-STO-004** — Every custom table that holds store-dependent content has `store_id` or a `_to_store` link table (SPEC-005 REQ-DB-050); queries filter by the current store.
- **REQ-STO-005** — Layouts: `layout.json` (SPEC-016) allows `stores: ["main","b2b"]` per assignment; default is all stores. SEO keywords unique per `(store, language)` (SPEC-013 REQ-SEO-003).
- **REQ-STO-006** — Admin: roles are not store-scoped in core; content managers can edit all stores. If store-level permission is required it is a new spec (events on admin models filtering `*_to_store`).

### Behaviour per store in extensions

- **REQ-STO-010** — Extension settings are saved with the store id (SPEC-004 REQ-EXT-031/ edge case); the admin form shows a store selector when more than one store exists (copy core pattern from `theme/default`:
  `store_id` GET param).
- **REQ-STO-011** — Caches are keyed with the store id (`ocs_<ext>.<entity>.<id>.<language>.<store>`, SPEC-001 REQ-ARCH-110); FPC key includes host (SPEC-019 REQ-PRF-022).
- **REQ-STO-012** — Order e-mails, invoices and sitemaps use store name/URL from the order/store settings, never from the default store.
- **REQ-STO-013** — Tags/consent (SPEC-017) and robots/sitemap (SPEC-013) are per store: `tags.json` entries have `stores`, `robots.txt.tpl` and the sitemap index are generated per host.
- **REQ-STO-014** — Payment/shipping extensions may be enabled per store only through geo-zone/`status` settings stored per store; no store-specific branches in code.

### Environments

- **REQ-STO-020** — Profiles are `local`, `staging`, `production` (SPEC-001 `APP_ENV`). The **only** allowed differences between them are listed in `custom/env/allowed-differences.json`:
  URLs/hosts, DB/credentials, mail transport, payment/shipping provider credentials and test-mode flags, tag IDs, analytics/ads enablement, error display/log level, cache TTL multipliers, `Disallow`/noindex policy,
  backup/jobs schedule, admin directory name, IP allow-lists, seed/test extensions inclusion. Everything else must be identical (settings registry compares).
- **REQ-STO-021** — `scripts/env-diff.php --a <env> --b <env>` (reads two DB connections or two settings exports) prints differences in `oc_setting`, `oc_extension`, `oc_event`, `oc_layout_*`, `oc_seo_url` counts,
  language/currency rows; any difference not covered by REQ-STO-020 is a failure (drift). Run before every release (SPEC-014 release runbook) between staging and production exports.
- **REQ-STO-022** — Settings export: `php scripts/settings-export.php [--store N] [--mask-secrets]` writes sorted JSON of `oc_setting` (+ extensions/events/layouts) with secret-looking keys masked
  (`password|secret|key|token|salt|encryption`), suitable for diffing and for the settings registry.
- **REQ-STO-023** — Production-only guards (SPEC-001 REQ-ARCH-062): HTTPS URLs, `config_error_display=0`, test modes off, robots allow, tags enabled. Non-production guards: robots `Disallow: /`, `X-Robots-Tag: noindex`, mail
  redirected to the sink or to a whitelist, payment test mode forced, banner "STAGING" in the theme header (view event on non-production).
- **REQ-STO-024** — Data copy between environments: production → staging copies DB and `image/catalog` via backup/restore (SPEC-014) followed by a **sanitisation script** (`scripts/sanitize-staging.php`): anonymise customers and
  orders PII (reuse DSR anonymiser), reset API keys, replace payment/shipping credentials with sandbox placeholders, set URLs, disable jobs that contact providers. Staging never receives real customer e-mail sending ability.
- **REQ-STO-025** — Never copy staging → production data; deployments move code (artefact) and run migrations only.

### Domains, HTTPS, redirects

- **REQ-STO-030** — Canonical host per store; `http→https`, `www` handling and trailing slash policy generated into the web-server config from `stores.json` (SPEC-003 REQ-BLD-032, SPEC-013 REQ-SEO-005).
- **REQ-STO-031** — Cookie domain: session cookie is host-only (no `Domain=` attribute) so stores on different hosts do not share sessions; language/currency cookies set by core use `HTTP_HOST`.
- **REQ-STO-032** — Cross-store links (e.g. B2B shop link in header) are built from `stores.json` URLs, not typed in templates.
- **REQ-STO-033** — Wildcard/preview hosts for review (`pr-123.example.com`) are not supported by core store resolution; previews use staging only.

## Non-functional requirements

- **REQ-STO-040** — Adding a store requires: a JSON entry, `.env` URL, migration run, content assignment in admin — no code changes (acceptance test).
- **REQ-STO-041** — Store resolution adds no custom queries; additional per-store config reads come from the already loaded `$this->config`.

## Data requirements

`custom/stores/stores.json`, `custom/env/allowed-differences.json`; `oc_store` and store-scoped rows; no new tables.

## Edge cases

- Two stores share a host but differ by path (`/shop-b/`) → supported by core resolution (path included) but not recommended; FPC key includes full path.
- Store URL changed → settings and `oc_store` updated by migration; old URL redirects via web server rules; SEO redirects table unaffected (path-based).
- Unknown host hits the server → falls back to store 0 content with `config_url = HTTP_SERVER`; web server rejects unknown hosts (default vhost returns 444/404) to avoid duplicate content.
- Product linked to no store → invisible everywhere; doctor reports (SPEC-012).
- Customer registered in store B logs into store A → core allows (global `oc_customer` e-mail uniqueness); policy decision recorded in store-config spec.

## Acceptance criteria

- [ ] `stores.json` with a second store produces `oc_store` row and store-scoped settings after `migrate up`; second `up` is a no-op.
- [ ] Adding the second store requires no PHP changes (REQ-STO-040).
- [ ] `env-diff` reports a deliberately changed non-allowed setting and ignores allowed differences.
- [ ] Staging sanitisation leaves no real e-mail/phone in customers/orders (fixture check).
- [ ] Non-production builds serve `Disallow: /` and the staging banner.
