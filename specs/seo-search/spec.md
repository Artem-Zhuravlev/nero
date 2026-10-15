# SPEC-013 — SEO, Sitemaps and Search

**Status:** draft
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-15
**Refines:** SPEC-001 section 15 · SPEC-008

## Platform facts (verified in `core/upload/`)

1. SEO URLs: `startup/seo_url` rewrites `_route_` using `oc_seo_url(store_id, language_id, query, keyword)`;
   `url->addRewrite` produces outgoing links when `config_seo_url=1`. Apache rewrite rule in
   `.htaccess.txt` sends unknown paths to `index.php?_route_=…`. `oc_seo_url` has non-unique
   keys on `query` and `keyword`.
2. ocStore bundles **SeoPro** (`system/library/seopro.php`, flag `config_seo_pro`): category
   path resolution, optional postfix, language detection from URL, trailing-slash and
   lower-case options (`config_seopro_addslash`, `config_seopro_lowercase`), parameter whitelist
   (`config_valide_param_flag`, `config_valide_params`), canonical/redirect validation (`validate()`),
   blog URLs.
3. Meta fields: `product_description.meta_title/meta_description/meta_keyword/meta_h1`; categories and
   information similar; `noindex` flags on products/categories (default 1).
4. Header controller emits `robots` (variable `robots` in `common/header.twig`), canonical/prev/next
   via `document->addLink`, Open Graph tags (`og_url`, `og_image`).
5. Sitemap: `.htaccess.txt` maps `sitemap.xml` -> `extension/feed/google_sitemap` (needs
   `feed_google_sitemap_status`); Google Base feed `googlebase.xml` -> `extension/feed/google_base`.
   An HTML sitemap page exists (`information/sitemap`). `robots.txt` is a static file.
6. Search: `product/search` -> `catalog/model/catalog/product->getProducts()` with `filter_name`
   (each word `pd.name LIKE '%word%'`, optionally description via `filter_description`), `filter_tag`
   (`pd.tag LIKE`), category/sub-category filters. **No FULLTEXT index, no relevance ranking, no
   typo tolerance**; blog articles use `MATCH … AGAINST`.

## Problem

Search-engine visibility is a primary growth channel and the platform's defaults (noindex
defaults, LIKE search, static robots, CDN fonts, non-unique slugs) need an explicit policy. SeoPro
also changes URL behaviour in ways that break silently when settings change.

## Purpose

Fix the URL scheme, indexation rules, metadata generation, structured data, sitemaps/robots, redirect
policy and the search approach, with checks that catch regressions.

## Scope

URL structure, slug rules, SeoPro settings, canonical/hreflang/robots, metadata templates,
structured data, sitemap generation, redirects, on-site search (relevance and performance), 404 handling.

## Out of scope

Content strategy, link building, analytics tags (marketing spec), external search engines.

## Functional requirements

### URL scheme

- **REQ-SEO-001** — Settings fixed by migration: `config_seo_url=1`, `config_seo_pro=1`, `config_seopro_lowercase=1`,
  `config_seopro_addslash=0` (no trailing slash; canonical form), postfix none. The choice is recorded
  in `docs/settings-registry.md` and checked by `smoke` (REQ-SEO-050).
- **REQ-SEO-002** — URL patterns (per language, language prefix or domain decided in store-config spec):
  `/<category-path>` (nested keywords, SeoPro path mode), `/<category>/<product-slug>` canonical for
  products (SeoPro with category path) **or** `/<product-slug>` — one mode, recorded; information
  `/<slug>`; manufacturer `/brands/<slug>`; blog `/blog/<slug>`; fixed pages (cart, checkout, contact,
  search, login/account) have explicit keywords created by migration (`$m->seoUrl->set`), never left to
  `index.php?route=`.
- **REQ-SEO-003** — Slug rules: lowercase Latin transliteration (SPEC-008 REQ-I18N-021), digits and `-`,
  <= 70 chars, no stop-word stripping, unique per `(store, language)` across **all** entity types (not just per
  type) — enforced by an event on SEO keyword saves in admin models (`addProduct/editProduct/...Category/
  Information/Manufacturer` `before`) and by the catalogue doctor (SPEC-012).
- **REQ-SEO-004** — Slug changes create a 301 redirect from the old URL: table `oc_ocs_seo_redirect(old_path
  UNIQUE, new_query, language_id, code 301|302|410, hits, date_added)`, consulted by a
  `controller/startup/seo_url/before`-style event (route `error/not_found` handler) before showing 404.
  Redirect chains are collapsed (A->B->C becomes A->C).
- **REQ-SEO-005** — Only one hostname and scheme is canonical (`https://<host>`); `www`/non-`www` and `http` redirect
  with 301 at web server level (build generates the rules from `APP_URL`).

### Indexation and canonical

- **REQ-SEO-010** — Index policy: indexable = active product/category/information/manufacturer/blog pages;
  `noindex,follow` = search results, filtered/sorted/paginated listing variants beyond page 1 with
  parameters (`sort`, `order`, `limit`, `filter`, `page>1` handled per REQ-SEO-011), compare, account, cart,
  checkout, login, wishlist, empty categories.
- **REQ-SEO-011** — Pagination: `page=N` pages are indexable with self-canonical (`rel=canonical` to themselves),
  `rel=prev/next` kept (core emits), `page=1` canonicalises to the bare URL (core redirect
  rule exists in `.htaccess.txt`). Sorting/limit parameter variants canonicalise to the bare list URL.
- **REQ-SEO-012** — `robots` meta value is set in one place: a `view/common/header/before` handler
  (`ocs_seo`) that computes it from the route + `noindex` flags + REQ-SEO-010, overriding the
  value derived by core only for non-indexable cases.
- **REQ-SEO-013** — `robots.txt` is generated from `custom/seo/robots.txt.tpl` by the build
  (`{{APP_URL}}` sitemap line; disallows `/<ADMIN_DIR>/`, `/system/`, `/index.php?route=`, cart,
  checkout, account, search, `*?sort=`, `*?order=`, `*?limit=`, `*?filter=`, `*?tracking=`),
  `Allow: /image/`, `/catalog/view/`; non-production environments serve `Disallow: /`.
- **REQ-SEO-014** — `hreflang` (SPEC-008 REQ-I18N-022) and canonical use the same URL builder; they are always absolute.

### Metadata

- **REQ-SEO-020** — Metadata templates in `custom/seo/meta-templates.json` per entity and language:
  `title`, `description`, `h1` with tokens (`{name}`, `{category}`, `{manufacturer}`, `{price}`, `{store}`).
  Fields filled by editors win; templates apply only when empty, via `view/<route>/before` handlers
  that set `document->setTitle/setDescription`. Lengths: title <= 60, description <= 160 (truncate at word boundary).
- **REQ-SEO-021** — One `h1` per page equal to `meta_h1` (ocStore) or name.
- **REQ-SEO-022** — Open Graph/Twitter tags: `og:title`, `og:description`, `og:image` (main product image at
  1200x630 crop via resize role `og`), `og:type` product/website, `og:locale`.

### Structured data

- **REQ-SEO-030** — JSON-LD generated by library `Ocs\Seo\JsonLd` and injected by view events:
  `Organization` + `WebSite` (with `SearchAction`) on home; `BreadcrumbList` on category/product/information;
  `Product` with `offers` (price in the displayed currency, `priceCurrency`, `availability` from stock
  status/quantity, `sku`=model, `gtin` if available, `brand`, `aggregateRating` only when reviews
  exist) on product pages; `CollectionPage`/`ItemList` on categories (optional). Output validated with
  `JSON.parse` in tests; values escaped for `</script>`.
- **REQ-SEO-031** — Structured data contains only what is visible on the page; prices match displayed ones.

### Sitemaps

- **REQ-SEO-040** — `sitemap.xml` is a sitemap **index** generated by extension `ocs_sitemap` (route
  `extension/feed/ocs_sitemap`, type `feed`): per entity type and per 5 000 URLs files
  `sitemap-products-1.xml`, `-categories.xml`, `-information.xml`, `-blog.xml`; each URL has `loc`,
  `lastmod` (date_modified), per-language `xhtml:link` alternates; only indexable URLs (REQ-SEO-010).
  Cached for 1 hour (`ocs_sitemap.*` keys) and invalidated on product/category mutation events.
- **REQ-SEO-041** — The core `google_sitemap` feed is disabled (`feed_google_sitemap_status=0`) when `ocs_sitemap`
  is active; the `.htaccess` rule is replaced by the build to point to `ocs_sitemap` (SPEC-003 template).
- **REQ-SEO-042** — Sitemap size limits (50 000 URLs / 50 MB) are asserted by a test with a synthetic catalogue.

### Redirects and errors

- **REQ-SEO-045** — 404 pages return HTTP 404 with the theme layout, search box and top categories; 410 for removed
  products when `oc_ocs_seo_redirect.code=410`; disabled products return 404 unless a redirect exists to the category.
- **REQ-SEO-046** — Legacy URLs from a previous site (if migrated) are loaded from `custom/seo/redirects.csv` by a
  migration into `oc_ocs_seo_redirect`.

### Search

- **REQ-SEO-050** — Smoke checks (SPEC-009): `/robots.txt` 200 with sitemap line; `/sitemap.xml` 200 well-formed XML;
  home has one `h1`, a canonical, JSON-LD parseable; a category URL ends without slash; `http://` redirects 301 to https
  (only when run against an https base).
- **REQ-SEO-060** — Search approach, staged: **stage 1** (default) uses core LIKE search on name and tag, plus an exact/prefix SKU (`model`) match added by an
  event on `catalog/view/product/search/before`-time data (results merged in the controller `after` event),
  with `filter_description` off by default.
  **Stage 2** (own spec, only when catalogue > ~2 000 products or LIKE latency > 300 ms): a search
  index table `oc_ocs_search_index (product_id, language_id, tokens TEXT, FULLTEXT(tokens))`
  maintained by product mutation events and used by an `ocs_search` model override through the
  event short-circuit (`model/catalog/product/getProducts/before` returning results) with relevance
  ordering (name hit > SKU > tag > description). External engines (Meilisearch/Elastic) are out of scope here.
- **REQ-SEO-061** — Search input is normalised: trimmed, collapsed whitespace, max 100 chars, lowercased by
  `utf8_strtolower`, Ukrainian apostrophe/quote variants unified (`'’ʼ`), layout-switch typo (Cyrillic typed
  with Latin layout) detected by a library `Ocs\Search\Layout` and offered as "did you mean".
- **REQ-SEO-062** — Search queries are logged (core `config_customer_search` -> `oc_customer_search`); a weekly
  runbook step (SPEC-014) exports zero-result queries for catalogue/synonym improvement. A synonyms table
  `oc_ocs_search_synonym(term, alt, language_id)` is applied at stage 1 as OR terms.
- **REQ-SEO-063** — Search endpoints are throttled: > 30 requests/min per IP -> HTTP 429 (event on
  `controller/product/search/before`, counters in cache).

## Non-functional requirements

- **REQ-SEO-070** — Metadata/JSON-LD handlers add < 10 ms and no extra DB queries on cached paths.
- **REQ-SEO-071** — Sitemap generation memory < 128 MB for 100 000 URLs (streamed output).
- **REQ-SEO-072** — Search p95 < 300 ms at stage 1 for catalogue <= 2 000 products on reference hardware.

## Data requirements

`oc_seo_url` (core, managed via helper), `oc_ocs_seo_redirect`, later `oc_ocs_search_index`,
`oc_ocs_search_synonym`; JSON/CSV sources under `custom/seo/`.

## Edge cases

- Two entities with the same keyword -> save rejected (REQ-SEO-003); existing duplicates reported by doctor.
- Product in several categories -> SeoPro picks one main path; canonical points to it; others `rel=canonical` to main.
- Category disabled -> its URL 404s; children redirected to parent by REQ-SEO-045 rule.
- Changing `config_seo_pro` flags in admin -> all URLs change; protected by migration-managed value and a
  smoke check of URL patterns.
- Staging site accidentally indexed -> build emits `Disallow: /` and `X-Robots-Tag: noindex` when `APP_ENV != production`.

## Acceptance criteria

- [ ] After migrate, settings equal REQ-SEO-001 and fixed pages have keywords in each language.
- [ ] Slug uniqueness across entity types enforced in admin (fixture test).
- [ ] Old slug -> 301 -> new slug; chain collapsing test passes.
- [ ] robots.txt, sitemap index and sub-sitemaps validate (XML well-formed, only indexable URLs).
- [ ] JSON-LD for a product validates structurally and matches the displayed price.
- [ ] Staging build serves `Disallow: /`.
