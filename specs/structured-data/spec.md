# SPEC-080 — Structured Data (schema.org JSON-LD) for All Page Types

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-013 (SEO/search), SPEC-045 (SEO markup, PDP), SPEC-054 (breadcrumbs)

## Problem

Only the product page carries JSON-LD (`Product` + `BreadcrumbList`), and that page also repeats the breadcrumbs as microdata, so
Google sees two competing `BreadcrumbList`s. Category, brand, blog, information, contact, home and search pages have no structured
data except microdata breadcrumbs. The product rating is the core's rounded integer, reviews are not exposed as `Review`, special
prices have no `priceValidUntil`, and there is no `Organization` / `WebSite`. Text is cut from HTML ad hoc, so escaping depends on
each call site.

## Purpose

Every storefront page type prints valid, escaped schema.org JSON-LD that follows Google Search structured-data guidelines as of
2026-10, produced by one library and one event, so no template builds JSON by hand.

## Scope

Page types and their markup (one `<script type="application/ld+json">` with an `@graph` per page, in `<head>`):

| Page (route)                                   | Graph                                                                                   |
| ---------------------------------------------- | --------------------------------------------------------------------------------------- |
| every page                                     | `Organization` (name, url, logo, `sameAs`, `contactPoint`, address) + `WebSite` (name, url, `publisher`) |
| `common/home`                                  | `WebPage`, plus `ItemList` per product carousel module on the page                      |
| `product/category`, `product/manufacturer.info`, `product/special`, `product/search` | `CollectionPage` (`SearchResultsPage` for search) + `BreadcrumbList` + `ItemList` of the products on the page |
| `product/manufacturer`                         | `CollectionPage` + `BreadcrumbList` + `ItemList` of brands                              |
| `product/product`                              | `Product` (+ `Offer`, `AggregateRating`, `Review[]`, `brand`, `mpn`/`sku`, `image[]`) + `BreadcrumbList` + `ItemList` for accessories / similar carousels |
| `blog/article`                                 | `BlogPosting` (headline, image, dates, author, publisher) + `BreadcrumbList`            |
| `blog/category`, `blog/latest`                 | `Blog`/`CollectionPage` + `BreadcrumbList` + `ItemList` of articles                     |
| `information/information`                      | `WebPage` + `BreadcrumbList`                                                            |
| `information/contact`                          | `ContactPage` + `BreadcrumbList`                                                        |
| every other page with breadcrumbs (cart, compare, account…) | `WebPage` + `BreadcrumbList`                                               |

## Out of scope (and why)

- `FAQPage` and `QAPage`: Google limits FAQ rich results to authoritative government/health sites; product Q&A is not a QAPage.
- `AggregateRating`/`Review` on `Organization`/`LocalBusiness`: self-serving reviews are not eligible for rich results (Google policy).
- `WebSite` `SearchAction`: Google dropped the sitelinks search box (2024); not emitted.
- `VideoObject` for the product YouTube video: Google requires `uploadDate`, which the shop does not store.
- `shippingDetails`, `hasMerchantReturnPolicy` on `Offer`: optional, need shop-wide policy data that does not exist yet (follow-up).
- Admin UI for editing markup; Merchant Center feed.

## Functional requirements

- **REQ-SD-001** — One library `Ocs\StructuredData` builds every node; one view-after event inserts the finished script before
  `</head>` of full-page output only (never for partials, AJAX, or when the setting is off). Templates build no JSON.
- **REQ-SD-002** — `Organization` + `WebSite` on every page: `@id` (`<home>/#organization`, `<home>/#website`), name = store name,
  `url`, `logo` (absolute URL), `sameAs` = http(s) profile URLs from the contact links (SPEC-047), `contactPoint[]` with
  `telephone` in E.164-like form and `contactType: customer service`, `address` as `PostalAddress` only when the address is set.
- **REQ-SD-003** — `BreadcrumbList` is emitted as JSON-LD from the controller's `breadcrumbs` for every page that has them: 1-based
  `position`, `name`, absolute `item` for every item except the last (the current page). The microdata breadcrumb markup is
  removed from all templates, so each page has exactly one `BreadcrumbList` (revises REQ-BRC-001 of SPEC-054 from microdata to
  JSON-LD; REQ-BRC-002..005 stay: current page is a non-link `<span aria-current>`, "Home" word, identical markup).
- **REQ-SD-004** — `Product`: `name`, `url`, `@id`, `image[]` (main + gallery, absolute), `description` (plain text ≤ 5000), `sku`,
  `mpn` (model), `gtin` only if the field holds 8/12/13/14 digits, `brand`, and `offers`: `price` (taxed, dot decimal, 2 places),
  `priceCurrency` (ISO 4217, the session currency), `availability` (`InStock`/`OutOfStock` by stock quantity, full
  schema.org URL; the pre-order button is a UI state and is not mirrored), `itemCondition` `NewCondition`, `url`, `seller` -> organization `@id`, and `priceValidUntil` (end date of the
  active special, else omitted). No `offers` when the price is hidden for guests.
- **REQ-SD-005** — Reviews: `aggregateRating` with `ratingValue` (real average, 1 decimal), `bestRating: 5`, `worstRating: 1`,
  `reviewCount` — only when ≥ 1 approved review exists. Up to 10 newest approved reviews as `Review` (`author` `Person` name,
  `datePublished` ISO 8601, `reviewBody`, `reviewRating` `Rating` 1..5). Never invented, never emitted for products without reviews.
- **REQ-SD-006** — Listing pages: `ItemList` with `itemListElement` of `ListItem` (`position`, `url`) for the products shown on the
  page; positions continue across pagination (`(page-1)*limit + i`); product carousels on the product page (accessories, similar)
  and on the home page are separate `ItemList`s named after the block heading. Items beyond 50 per list are dropped.
- **REQ-SD-007** — Articles: `BlogPosting` with `headline` (≤ 110 chars), `image[]`, `datePublished`, `dateModified`, `author`
  (`Person` or the organization), `publisher` -> organization, `mainEntityOfPage`, `description`.
- **REQ-SD-008** — Page nodes (`WebPage`/`CollectionPage`/`SearchResultsPage`/`ContactPage`/`Blog`) carry `@id` = canonical URL,
  `url`, `name`, `inLanguage` (active language code, BCP 47), `isPartOf` -> website, `breadcrumb` -> the list, `about`/`mainEntity`
  -> the product/article/list node of the page.
- **REQ-SD-009** — Settings (theme `ocs`, group "SEO"): `theme_ocs_schema_status` (default on), `theme_ocs_schema_org_name`
  (default store name), `theme_ocs_schema_org_logo` (default store logo). Added through a numbered migration, idempotent.

## Non-functional requirements

- **REQ-SD-010** — Escaping and validity (all of these, in the library, not in call sites):
  1. text values: `strip_tags`, `html_entity_decode` once (no double-encoding of `&amp;`), whitespace collapsed, invalid UTF-8
     scrubbed, truncated on a character boundary (`mb_substr`), empty -> property dropped;
  2. URLs: made absolute, scheme must be `http`/`https`, same host family as the store for `url`/`item`/`@id`, else dropped;
  3. numbers/dates: price `number_format(…, 2, '.', '')`, rating 1 decimal, dates `DATE_ATOM`, currency validated `^[A-Z]{3}$`,
     enums only from a whitelist of schema.org URLs;
  4. encoding: `json_encode` with `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE |
     JSON_UNESCAPED_SLASHES`, so `</script>`, `<!--` and quotes cannot end the script element; on encoding failure nothing is
     printed (page still renders);
  5. nodes missing a Google-required property (e.g. `Product` without `name` or without `offers`/`review`/`aggregateRating`,
     `ListItem` without `url`, `Review` without `author`/`reviewRating`) are dropped entirely rather than emitted incomplete;
  6. markup always matches visible content (same price, availability, rating, review counts as the page shows).
- **REQ-SD-011** — Performance: no extra DB query on listing, home, information and contact pages beyond one small settings read
  (default contact language); the product page adds the product row, ≤ 10 reviews and the special end date; the article page adds the article row; the whole thing is skipped for non-`GET` and AJAX requests.
- **REQ-SD-012** — No change to `core/`; compatible with PHP 7.4–8.2 as the rest of `custom/`; no new third-party code.
- **REQ-SD-013** — The theme archive is rebuilt (`npm run pack:theme`) (SPEC-051).

## Data requirements

Three settings (REQ-SD-009), written by a numbered migration `0011_structured_data_settings.php`; one event
`ocs_structured_data` on `catalog/view/*/after`, registered in the same migration. The old product-only `schema()` in
`Ocs\ProductExtras` and `ocs_px.schema` in `product.twig` are removed.

## Interfaces

`Ocs\StructuredData::graph(array $nodes): string` (script body or `''`), node builders `organization()`, `website()`,
`breadcrumbs()`, `product()`, `itemList()`, `article()`, `page()`, sanitisers `text()`, `url()`, `price()`, `date()`.
Event handler `extension/ocs/events/structuredData` (existing `ocs` events class) maps `route` -> builders.

## Edge cases

- Product without price (guest-hidden) -> `Product` without `offers`; if it also has no reviews the node is dropped (REQ-SD-010.5).
- Product description containing `</script>`, quotes, emoji, `&amp;`, HTML -> plain text, safe in the script block.
- Category with one product page / empty category -> no `ItemList`.
- Breadcrumb trail with one item -> one `ListItem` without `item`.
- Multilingual store -> `inLanguage` and names follow the active language; URLs are the active-language URLs.
- SeoPro/`seo_url` pretty URLs -> `url` is read from `$this->url->link`, never rebuilt by hand.
- Custom domain / http->https -> `HTTPS_SERVER`/config decides the scheme; nothing hard-coded.
- Setting off -> no script, templates still render breadcrumbs HTML (without microdata).

## Acceptance criteria

- [ ] Every page type in the table prints exactly one JSON-LD script; its `@graph` parses as JSON and has no duplicate `@id`.
- [ ] No page has `itemscope`/`itemprop` microdata left; no page has two `BreadcrumbList`.
- [ ] PHP tests feed hostile strings (`</script><script>alert(1)</script>`, `"`, `'`, `&amp;`, `<!--`, invalid UTF-8, 10k chars,
      `javascript:` URL) and the output stays valid JSON containing no raw `<`, `>`, `&` and no `</`.
- [ ] Required-property checks per type (REQ-SD-010.5) are asserted by tests (`TEST-SD-…`).
- [ ] `npm run verify` and `npm run test:php` are green; theme archive rebuilt.
- [ ] Manual, after deploy: Google Rich Results Test and validator.schema.org show no errors for a product, category, article,
      information, contact and home URL (cannot be run offline; recorded in the task list as the last, user-run step).

## Decisions (answered by the owner, 2026-10-27)

1. Breadcrumbs: JSON-LD only, microdata stripped from the 44 templates.
2. Home-page carousels: `ItemList` for every product module on the home page.
3. Organization address / phones / profiles: from the SPEC-047 contact settings (the store must fill them in).

## Implementation notes

- Admin strings exist for en-gb and uk-ua only (the project has no ru-ru admin language pack).
- `custom/VERSION` is not bumped (earlier specs left it at 1.0.0); the archive is rebuilt.
- `scripts/gen-theme-templates.mjs` now generates the plain breadcrumb block.
- REQ-PRD-040 of SPEC-035 (product-only JSON-LD) is superseded by this spec.
