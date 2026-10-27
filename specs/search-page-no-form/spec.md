# SPEC-078 — Search Page Without the Search Form

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-013 (SEO/search), SPEC-006 (theme)

## Problem

`product/search` repeats a form (keyword, category, sub-category, search-in-description, button) right under the header search field.

## Functional requirements

- **REQ-SRCH-001** — The `product/search` template renders no search form block (keyword input, category select, checkboxes, button) and no script for it; heading, results, sorting, limit, pagination are unchanged.

## Acceptance

- **TEST-SRCH-001** — verifies REQ-SRCH-001: `npm run verify` green; template has no `input-search`/`button-search`; archive rebuilt.
