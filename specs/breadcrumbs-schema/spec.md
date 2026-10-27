# SPEC-054 — Breadcrumbs: schema.org Markup, No Self-Link, "Home" as a Word

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-006 (theme), SPEC-013 (SEO/search)

## Problem

Storefront breadcrumbs (44 templates of the `ocs` theme and the category-wall page) render every item as a link,
including the last one, which points at the page the visitor is already on. They carry no structured data, so
search engines cannot build breadcrumb snippets. The first item is a house icon, which is unclear and is not read
by screen readers or crawlers.

## Purpose

Breadcrumbs are a valid schema.org `BreadcrumbList`, the current page is plain text, and the first item reads
"Главная" / "Головна" / "Home" according to the active language.

## Scope

- All storefront templates that print `breadcrumbs` (theme `ocs`, plus `ocs_category_wall` page template).
- One language string for the home item (ru-ru, uk-ua, en-gb).
- CSS for the non-link current item.

## Out of scope

- Admin breadcrumbs.
- Changing which items a controller puts into the trail.
- JSON-LD `BreadcrumbList` (microdata is used; the product page's existing JSON-LD stays as is).

## Functional requirements

- **REQ-BRC-001** — Every storefront breadcrumb list is marked up as `schema.org/BreadcrumbList` (microdata):
  `itemListElement` / `ListItem` with `name` and 1-based `position`; non-last items also carry `item` (the URL).
- **REQ-BRC-002** — The last item (the current page) is not a link: it is rendered as `<span>` text with
  `aria-current="page"`, with no `href`.
- **REQ-BRC-003** — The home item shows the word "Главная" (ru-ru), "Головна" (uk-ua), "Home" (en-gb) instead of
  the house icon, taken from a language string, not hard-coded in templates.
- **REQ-BRC-004** — The markup is identical in every template (no page keeps the old icon/self-link markup).

## Non-functional requirements

- **REQ-BRC-005** — No change to `core/`; the theme looks unchanged apart from the text instead of the icon and the
  non-link current item (muted/dark text as today's `:last-child` style).

## Data requirements

New language key `ocs_breadcrumb_home` in `ocs_shell` storefront language files (ru-ru, uk-ua, en-gb).

## Interfaces

Storefront view event (existing `ocs_shell` events class) normalises `$data['breadcrumbs']` for every template:
replaces the core `fa-home` text with the language string. Templates iterate `breadcrumbs` with `loop.last`.

## Edge cases

- Page with a single breadcrumb item -> it is the current page: plain text, position 1.
- Controller already puts a plain-text home item -> left unchanged.
- Breadcrumb text with `&amp;`/HTML (autoescape is OFF) -> printed as today, `name` unchanged.

## Acceptance criteria

- [ ] No `<a>` to the current page inside `.breadcrumb` on category, product, information, blog, account, cart pages.
- [ ] Google Rich Results / schema validator reads a `BreadcrumbList` with correct positions.
- [ ] First item reads "Главная"/"Головна"/"Home" per language; no `#i-home` in breadcrumbs.
- [ ] `npm run verify` green; theme archive rebuilt.
