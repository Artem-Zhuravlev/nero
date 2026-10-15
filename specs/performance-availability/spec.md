# SPEC-019 — Performance, Scalability and Availability

**Status:** draft
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-15
**Refines:** SPEC-001 section 13, SPEC-006 REQ-THM-042, SPEC-013, SPEC-014

## Platform facts (verified in `core/upload/`)

1. Database engines: 157 of 159 core tables are **MyISAM** (table-level locks; a write blocks reads on that table); `oc_session` and one other table are InnoDB.
   Hot write tables on storefront traffic: `oc_session` (InnoDB), `oc_cart`, `oc_customer_online` (if enabled), `oc_statistics`, `oc_product.viewed` (updated on every
   product view by `updateViewed`), `oc_customer_activity` (if enabled), `oc_api_session`.
2. Cache: framework cache with engines file/apc/mem/memcached/redis, default `file` with `cache_expire=3600`; Twig template cache in `DIR_CACHE/template/`; image thumbnails
   in `image/cache/`; PHP opcache is a server setting.
3. `catalog/model/catalog/product::getProducts()` is built from large SQL with `LIKE`, joins on `product_to_category`, specials/discounts subqueries; `getTotalProducts()`
   repeats it; both run on category/search pages.
4. Every request runs all pre-actions: session start (DB read/write), store lookup query, **load of all `oc_setting` rows** for the store, language list query, currency,
   customer/cart/tax setup, SEO URL lookup(s) — a baseline of dozens of queries before the page controller.
5. `startup/seo_url` performs one `oc_seo_url` query per URL segment; indexes exist on `query` and `keyword` (non-unique).
6. The default theme loads jQuery 2.1.1, Bootstrap 3, Font Awesome and a Google Fonts stylesheet on every page.

## Problem

Default OpenCart performance degrades sharply with catalogue size and concurrency (MyISAM locks, heavy baseline, LIKE search, no page cache). Without budgets and measurements, the
store gets slow after launch, and Core Web Vitals hurt SEO and conversion.

## Purpose

Set measurable performance and availability targets, the allowed optimisation techniques that respect "core is read-only", and the measurement/regression process.

## Scope

Budgets (server, DB, frontend), measurement tooling, caching layers, DB tuning and conversion guidance (engines/indexes on **our** tables and recommended server config), full-page cache
for anonymous traffic, capacity planning, availability targets, degradation behaviour.

## Out of scope

Hosting vendor selection, CDN contracts, horizontal scaling infrastructure (documented as a later option).

## Functional requirements

### Budgets

- **REQ-PRF-001** — Server-side budgets (reference production profile, anonymous traffic, warm caches): home, category (24 products), product, cart: p50 <= 250 ms, p95 <= 600 ms
  TTFB at 20 concurrent users; checkout steps p95 <= 800 ms excluding external provider time. DB queries per request: home <= 60, category <= 90, product <= 80 (the core baseline
  of fact 4 counts) — measured, not assumed.
- **REQ-PRF-002** — Frontend budgets (mobile profile, 4G throttling): LCP <= 2.5 s, CLS <= 0.1, INP <= 200 ms; transfer size home <= 800 KB, product <= 1 MB; JS <= 150 KB gz
  total including third parties (SPEC-017 REQ-MKT-005); requests <= 40.
- **REQ-PRF-003** — Admin: product list p95 <= 1.5 s with 10 000 products; product save p95 <= 2 s.
- **REQ-PRF-004** — Budgets are stored in `custom/perf/budgets.json` and every performance-affecting spec cites the budget lines it must keep.

### Measurement

- **REQ-PRF-010** — `node scripts/perf-bench.mjs --base-url <url> --scenario <file> [--users N --duration S]` is a dependency-free load generator (Node http, keep-alive) running scenarios in
  `custom/perf/scenarios/*.json` (URL lists with weights: home, categories, products, search terms, cart add), reporting RPS, p50/p95/p99, error rate, and saving results to
  `var/perf/<timestamp>.json`; compares against the previous result and budgets (exit 1 on regression > 20 % or budget breach).
- **REQ-PRF-011** — Query profiling: `ocs_ops` debug event (SPEC-014 REQ-OPS-014) can output per-request query count/time and top-10 slow queries (with `EXPLAIN` in local); a script
  `scripts/perf-queries.mjs` aggregates `ocs-debug.log` to the heaviest routes.
- **REQ-PRF-012** — Frontend: Lighthouse CI is an optional manual step documented in the release checklist; results (scores for home, category, product) recorded in
  `docs/perf-log.md` per release.
- **REQ-PRF-013** — A seeded **performance dataset** generator (`scripts/seed-perf.php --products 5000 --categories 80`) creates a realistic catalogue through admin models (SPEC-012
  AdminBoot) for benchmarking; used only in local/CI-like environments.

### Caching strategy (layers, in order of application)

- **REQ-PRF-020** — *Opcode*: OPcache enabled in production with `validate_timestamps=0` and cache reset on each deploy (documented runtime profile, `docs/runtime-profile.md`).
- **REQ-PRF-021** — *Settings and lookups*: custom per-request lookups are cached in the framework cache (SPEC-001 REQ-ARCH-110). Core's settings load cannot be cached without patching core;
  accepted baseline.
- **REQ-PRF-022** — *Full-page cache (FPC) for anonymous GET traffic*: extension `ocs_fpc` serves cached HTML for allow-listed routes (home, category, product, information, blog) when the
  request has no customer session cookie with items/login, no `tracking`/currency/language mismatch, and no query beyond an allow-list (`page`, `sort`, `order`, `limit`, `path`, `product_id`).
  Cache key = host + language + currency + path + query allow-list; storage = files under `DIR_CACHE/fpc/` (TTL 10 min, configurable); `X-OCS-Cache: HIT|MISS|BYPASS` header.
  Hooked at the earliest event available (`controller/*/before` of the page route, returning the cached output) — measured to confirm that it skips controller work; if the pre-actions
  cost dominates, the plan allows moving the check into an `auto_prepend`-style entry in the **web-server config** (not core) documented in the runtime profile.
- **REQ-PRF-023** — FPC invalidation: product/category/information/banner/setting mutations (admin model events) delete affected keys or the whole FPC directory (simple mode); stock changes
  and price specials expiring are handled by TTL; orders do not invalidate. Personalised fragments (cart count, login links) are rendered client-side from a small uncached endpoint
  (`extension/ocs_fpc/fragments`, JSON, `Cache-Control: no-store`) so cached HTML is identical for everyone.
- **REQ-PRF-024** — FPC must never cache: responses with `Set-Cookie` of customer data, pages with CSRF/tokens, error pages (except 404 with short TTL 60 s), POST, pages for logged-in
  customers, preview URLs (SPEC-016), maintenance mode.
- **REQ-PRF-025** — HTTP caching: static assets `Cache-Control: public, max-age=31536000, immutable` when versioned via `?v=` (SPEC-006 REQ-THM-022); images `max-age=2592000`; HTML
  `no-cache` unless FPC served (then `max-age=0, s-maxage` irrelevant without CDN); compression gzip/brotli at the web server (core `config_compression` stays 0 to avoid double compression).
- **REQ-PRF-026** — Image delivery: WebP variants generated by a job from `image/cache` originals when GD supports it (`ocs_images` extension, `<picture>` in theme templates); responsive
  `srcset` with the sizes listed in SPEC-006 REQ-THM-030; max source pixel size enforced on upload (SPEC-012 REQ-CAT-030).

### Database

- **REQ-PRF-030** — Our own tables use InnoDB and index every lookup column (SPEC-005 REQ-DB-051). We do not convert core tables from MyISAM to InnoDB in migrations (SPEC-001 REQ-ARCH-053) —
  but the **runtime profile** documents an optional, separately-approved procedure (`docs/runbooks/myisam-to-innodb.md`) converting hot tables (`oc_cart`, `oc_product`, `oc_setting`,
  `oc_customer_online`, `oc_statistics`) after a risk review, since it changes locking behaviour and full-text availability; the procedure is a spec of its own before execution.
- **REQ-PRF-031** — Hot-write mitigations that need no schema change: disable `config_customer_online`, `config_customer_activity`, `config_customer_search` (SPEC-015); product view
  counters are batched: an event on `catalog/model/catalog/product/updateViewed/before` returns early (short-circuit) and increments a cache counter, flushed to `oc_product.viewed` by a job
  every 10 minutes (SPEC-014 jobs). Removes a table-lock write per product view.
- **REQ-PRF-032** — Recommended MySQL/MariaDB settings for the production profile (`docs/runtime-profile.md`): `innodb_buffer_pool_size` ≥ 50 % of RAM on a dedicated DB host, `key_buffer_size`
  sized to MyISAM indexes (core tables), `max_connections` matched to PHP workers, slow query log at 0.5 s, `sql_mode` compatible with core (`''`; the installer itself sets
  `@@session.sql_mode = ''`) .
- **REQ-PRF-033** — Missing-index review: `scripts/db-indexes-check.php` lists queries from `ocs-debug.log` running > 100 ms and whether our tables cover them; for core tables it only
  reports (no changes). Adding an index to a core table, if ever needed, is an explicit exception recorded in a spec (SPEC-001 REQ-ARCH-053).
- **REQ-PRF-034** — Search performance follows SPEC-013 (stage 2 triggers).

### Frontend

- **REQ-PRF-040** — Critical rendering: inline critical CSS for above-the-fold (generated at build for home/category/product templates, <= 14 KB), the rest loaded with
  `media="print" onload` pattern or `rel=preload`; fonts self-hosted with `font-display: swap`, preload of the main font; no render-blocking third-party.
- **REQ-PRF-041** — JS: jQuery/Bootstrap kept (compatibility with core `common.js`); theme JS deferred where core markup allows; no additional large libraries (carousel/lightbox use
  CSS or small vendored files with sizes recorded in `ASSETS.md`).
- **REQ-PRF-042** — Layout stability: images and banners have width/height attributes; reserved space for dynamic fragments (cart count).

### Availability and degradation

- **REQ-PRF-050** — Availability target (production, single region): 99.5 % monthly for storefront browsing, 99.9 % for the health endpoint's own process; planned maintenance windows announced
  and use maintenance mode (SPEC-014 runbook).
- **REQ-PRF-051** — Degradation order under load or partial outage: (1) serve FPC for anonymous traffic even if stale up to 1 h when DB errors occur (stale-while-error, header
  `X-OCS-Cache: STALE`); (2) disable search and heavy filters returning 503 + Retry-After; (3) hide third-party providers via circuit breaker (SPEC-018); (4) maintenance page with
  static HTML for total failure (file served by web server `ErrorDocument 503`).
- **REQ-PRF-052** — Capacity plan (`docs/capacity.md`): requests/min, PHP workers (= RAM budget / 60 MB average), DB connections, disk growth per 1 000 orders and images; reviewed quarterly
  and before campaigns; load test at 2× expected peak before launches.
- **REQ-PRF-053** — Single points of failure are listed with mitigation/recovery time in `docs/capacity.md` (DB, storage, mail, scheduler).

### Regression control

- **REQ-PRF-060** — Release checklist (SPEC-009 REQ-QA-052) includes: `perf-bench` on the standard scenario against staging with the perf dataset, comparison with the last release, budget check;
  a failing budget blocks release unless an approved waiver (spec note with owner and expiry).
- **REQ-PRF-061** — Every new extension declares in its spec its expected per-request cost (queries, ms) and is measured with the debug event before approval (SPEC-004 REQ-EXT-081).

## Non-functional requirements

- **REQ-PRF-070** — Perf tooling needs no external services; reports are plain JSON/Markdown in the repo/`var/`.
- **REQ-PRF-071** — FPC adds < 3 ms on a MISS and serves a HIT in < 15 ms server time.
- **REQ-PRF-072** — Cache directories are bounded (FPC max 500 MB by LRU job; image cache pruned per SPEC-014).

## Data requirements

`custom/perf/budgets.json`, scenarios; FPC files under `DIR_CACHE/fpc/`; no DB tables except optional `oc_ocs_viewed_buffer` if cache counters are not durable (decision in implementation).

## Edge cases

- Personalised price (customer groups with special pricing) -> FPC bypass for logged-in users; `config_customer_price=0` means prices hidden until login, which FPC must respect (key includes the setting hash).
- Currency switch -> separate key per currency (cookie or session value read before lookup).
- Stale cache after a price special ends -> TTL bounded 10 min; documented.
- Product view counter loss on cache flush -> acceptable (statistic only).
- Cache stampede on popular pages after invalidation -> lock file per key, serve stale for the lock duration.

## Acceptance criteria

- [ ] `perf-bench` produces comparable runs; regression detection verified with a deliberately slowed fixture.
- [ ] FPC HIT served for anonymous category page with verified header; logged-in request bypasses; invalidation test after product edit.
- [ ] Product view counter batching removes the per-view write (query log shows no `UPDATE … viewed` in request).
- [ ] Budgets met on the perf dataset (5 000 products) in the reference profile, or waivers recorded.
- [ ] Degradation test: DB down -> anonymous cached pages still served (stale) and maintenance page for the rest.
