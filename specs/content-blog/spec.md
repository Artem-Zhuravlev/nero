# SPEC-016 — Content: Information Pages, Blog, Banners, Layouts

**Status:** draft
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-15
**Refines:** SPEC-006, SPEC-008, SPEC-013

## Platform facts (verified in `core/upload/`)

1. Information pages: `oc_information` (+ `_description`, `_to_store`, `_to_layout`); storefront `information/information`; list in footer via
   theme; core pages referenced by settings: `config_account_id` (3), `config_checkout_id` (5), `config_affiliate_id` (4), `config_return_id`.
2. **Blog (ocStore)**: tables `article` (`image`, `date_available`, `sort_order`, `article_review`, `status` default 0, `noindex` default 1,
   `viewed`, `gstatus`), `article_description`, `article_image`, `article_related*`, `article_to_blog_category`, `article_to_store`,
   `article_to_layout`, `blog_category` (+ `_description`, `_path`, `_to_store`, `_to_layout`), `review_article`. Controllers
   `catalog/controller/blog/{article,category,latest,menu}`, admin `blog/{article,category,review,setting}`, modules `blog_category`,
   `blog_featured`, `blog_latest`, `featured_article`. Search in articles uses `MATCH … AGAINST`.
3. Banners: `oc_banner` + `oc_banner_image(banner_image_id, banner_id, language_id, title, link, image, sort_order)`; modules `banner`,
   `slideshow`, `carousel` render them.
4. Layouts: `oc_layout`, `oc_layout_route`, `oc_layout_module` (positions `content_top|content_bottom|column_left|column_right`);
   17 default layouts (reference §6) including blog layouts 14–16.
5. Static HTML modules: `html` (instances in `oc_module` with per-language title/description).
6. Admin editor: bundled rich-text editor in admin forms (stores HTML in `*_description.description` as entity-encoded HTML).

## Problem

Content editors will create pages, articles and banners in admin; without rules the HTML becomes inconsistent, unsafe or
SEO-hostile, layouts drift between environments, and blog URLs/noindex defaults hide content.

## Purpose

Specify the content model, editorial rules, HTML sanitisation, banner/slider conventions, layout assignments as code and
blog behaviour.

## Scope

Information pages, blog, banners/sliders, html modules, layout composition, content sanitisation, editorial workflow.

## Out of scope

Content text itself, translations of marketing copy, landing-page builders.

## Functional requirements

### Information pages

- **REQ-CNT-001** — Fixed-meaning pages are declared in `custom/content/pages.json` (`key`, titles/body per language path,
  `sort`, `bottom`, SEO keyword per language, `status`, layout) and created through admin models by an idempotent
  migration; keys: `terms`, `privacy`, `delivery`, `payment`, `returns`, `about`, `contacts`. Their ids are mapped with codes
  (SPEC-012 REQ-CAT-002) and written to `config_account_id`, `config_checkout_id`, `config_return_id` by the same migration.
- **REQ-CNT-002** — Body text for these pages is stored in `custom/content/pages/<key>.<lang>.html` (reviewed in git) and imported
  by migration; subsequent admin edits are runtime changes; `scripts/content-export.mjs` can export current DB content back to
  files (for review/backup), never automatically overwriting.
- **REQ-CNT-003** — Legal pages carry a `version` (date) in `pages.json`; changing the file bumps the consent version
  (SPEC-015 REQ-CUS-030).

### HTML sanitisation and editorial rules

- **REQ-CNT-010** — Rich-text content saved from admin (information, blog articles, html modules, category/product descriptions) is
  sanitised on save by an event on the corresponding admin model `add*/edit*/before` using core's bundled **HTMLPurifier**
  (`system/helper/HTMLPurifier.php`) with an allow-list config `custom/content/purifier.json` (tags: p, h2–h4, ul, ol, li, a[href,rel,target],
  img[src,alt,width,height,loading], strong, em, br, blockquote, table family, figure/figcaption, iframe only for allow-listed
  hosts, no inline `style`/`script`/`on*`). Rejected content keeps the editor's value but strips the unsafe parts and shows a warning.
- **REQ-CNT-011** — `h1` is never allowed in body content (the page title is the `h1`); `h2` starts sections.
- **REQ-CNT-012** — Links: external links get `rel="noopener noreferrer"` and `target="_blank"` automatically; internal links are stored
  as relative URLs or `{{ link:route }}` placeholders resolved at render by a view event so domain changes do not break content.
- **REQ-CNT-013** — Images in content use the catalogue image rules (SPEC-012 REQ-CAT-030) and must have non-empty `alt`.
- **REQ-CNT-014** — Content length guidance (information pages >= 150 words, articles >= 400 words) is a warning in the admin form, not a block.

### Blog

- **REQ-CNT-020** — Blog categories and articles follow the same completeness rules as products (SPEC-012 REQ-CAT-011): title,
  description, meta title/description, `meta_h1`, SEO keyword, image, author-free (no per-article author in core).
- **REQ-CNT-021** — `noindex` default is 1 in core; an event on article save sets `noindex=0` when the article is published and complete,
  and the sitemap includes only indexable articles (SPEC-013 REQ-SEO-040).
- **REQ-CNT-022** — Article reviews (comments) are off by default (`article_review=0`); if enabled they are moderated (status 0 until
  approved) with captcha (`config_captcha_page` includes the blog review page) and rel="nofollow ugc" on links.
- **REQ-CNT-023** — Related products/articles are manual (core fields); no auto-related logic in v1.
- **REQ-CNT-024** — Blog list pagination/limits come from blog settings (`blog/setting`) set by migration; template overrides follow SPEC-006.
- **REQ-CNT-025** — Publishing schedule: `date_available` in the future hides the article (core). A job `blog-publish-warm` (SPEC-014) clears
  sitemap cache at publish time.

### Banners, sliders and html modules

- **REQ-CNT-030** — Banners are runtime content; their **placement** (which module instance in which layout/position) is code:
  `custom/content/layout.json` maps `layout route -> [ {module, instance code, position, sort} ]` and is applied by a migration via
  `$m->layout->moduleAdd` and `$m->module->upsert` (SPEC-005 REQ-DB-025/026). Instance names are stable (`home_slideshow`).
- **REQ-CNT-031** — Banner image requirements: width x height defined in the design spec per position; `alt`/title per language
  mandatory; link target must be internal route or allow-listed domain; max 5 slides; first slide image is the LCP image
  (explicit dimensions, no lazy loading; the rest lazy); autoplay pauses on hover and honours `prefers-reduced-motion`.
- **REQ-CNT-032** — The `html` module content passes the same sanitiser (REQ-CNT-010).
- **REQ-CNT-033** — Scripts/tracking snippets are **not** pasted into html modules; they belong to analytics (SPEC-017).

### Layouts as code

- **REQ-CNT-040** — Layout/route assignments for custom pages and the module composition of each standard layout are declared in
  `layout.json` and verified against the DB by `scripts/layout-check.php` (read-only diff); drift is a warning in `verify --env staging`
  and an error before release.
- **REQ-CNT-041** — New custom pages (`extension/ocs_*/…`) get their own layout row (name `OCS: <page>`) and `layout_route` entries via
  migration; they never reuse layout 4 (Default) by accident.

### Editorial workflow

- **REQ-CNT-050** — Roles: `Content manager` (SPEC-010) can edit information/blog/banners/html modules but cannot change layouts, settings,
  extensions, or SEO redirects table (own screen restricted to Administrator).
- **REQ-CNT-051** — Draft/preview: new items are created with `status=0`; preview via admin "view" link with `?preview=<signed token>`
  handled by a `ocs_content` event that overrides the status check for that request only (token = HMAC of id+expiry, 24 h).
- **REQ-CNT-052** — Changelog: content edits are recorded in the admin audit table (SPEC-010 REQ-ACC-030) with object type/id.

## Non-functional requirements

- **REQ-CNT-060** — Sanitiser adds < 30 ms per save; HTMLPurifier cache directory under `DIR_CACHE`.
- **REQ-CNT-061** — Content pages render with no more than 12 queries beyond layout/module baseline.

## Data requirements

Core content tables; `custom/content/*` JSON/HTML; optional `oc_ocs_content_preview` not needed (stateless tokens).

## Edge cases

- Editor pastes Word HTML with inline styles -> stripped; warning displayed.
- Page deleted in admin but referenced by `config_*_id` -> health check `layout-check`/doctor reports dangling setting ids.
- Two languages missing one translation -> parity of content (not UI) is a **warning** in `scripts/content-doctor.php` listing entities lacking a language row.
- Banner linking to a disabled page -> doctor reports broken links (HTTP status scan in local).
- Slider with 0 images -> module returns nothing (no empty container).

## Acceptance criteria

- [ ] `pages.json` migration creates all fixed pages with SEO keywords and sets the three config ids.
- [ ] Saving content with `<script>` or `onclick` strips them (test).
- [ ] Layout check shows no drift after `migrate up` on a fresh DB.
- [ ] Draft preview link works for an unpublished article and expires after 24 h.
- [ ] Blog article becomes indexable and appears in sitemap only when complete and published.
