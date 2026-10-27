# SPEC-079 — Super Search (language-independent, typo/layout/translit tolerant)

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-013, SPEC-028 · **Depends on:** SPEC-004, SPEC-005, SPEC-019

## Problem

Core search matches only the current language's name with `LIKE`. A visitor who types a product name in another language, on the
wrong keyboard layout (`фззду` for `apple`), with one typo (`aple`, `одиг`), or in transliteration (`эпл`, `аппле`) finds nothing.
Shop owners cannot teach search that "одежда" and "шмотки" mean the same. Naive fixes (`LIKE '%…%'` over every language, Levenshtein in SQL)
would overload the database.

## Purpose

One search engine for the storefront that finds products regardless of language, layout, one-letter mistakes and transliteration, with
admin-defined synonyms, answering from an indexed token table with bounded work per request.

## Scope

- New extension `ocs_super_search` (module): search service in `system/library/ocs/`, token index, admin settings page, reindex.
- Consumers: the AJAX suggest endpoint (SPEC-028) and the `product/search` results page, both only when the module is enabled.

## Out of scope

Elasticsearch/Sphinx, morphology/stemming dictionaries, spoken-language NLP, searching articles/blog (products only; categories and brands
keep the SPEC-028 name match), learning from click data.

## Functional requirements

- **REQ-SSR-001** — A product is found by its name in ANY installed language, by model, SKU, brand name, category names and tags, regardless
  of the visitor's current language.
- **REQ-SSR-002** — Wrong layout: a query typed on the EN/RU/UK layout is also searched as converted to the other two layouts (`ghbdtn` -> `привет`,
  `фззду` -> `apple`).
- **REQ-SSR-003** — Transliteration: Cyrillic (ru/uk) and Latin spellings are compared through a shared phonetic key, so `apple`, `эпл`, `аппле`,
  `епл` match the same products; the same works the other way (`yabluko` -> `яблуко`).
- **REQ-SSR-004** — Typo tolerance: one edit (insert, delete, replace, swap of neighbours) is allowed on terms of 4+ characters
  (`aple`, `одиг` -> `одяг`); 0 edits below 4 characters; never more than one edit.
- **REQ-SSR-005** — Multi-word queries: every word must match (AND); each word may be matched by any of the strategies above independently.
- **REQ-SSR-006** — Ranking: exact term > layout-converted > synonym > phonetic > fuzzy; ties by name-field over model/sku over other fields, then
  `sort_order`. Stock/price sorting of the search page is unchanged.
- **REQ-SSR-007** — Synonyms: admin textarea "Synonyms". Syntax: groups joined with `=` (`одежда=шмотки=одяг`), groups separated by new line or comma
  (`одежда=шмотки,носки=гольфы`). Groups are symmetric, case-insensitive, may contain multi-word phrases, and apply to name/model/tags
  of all languages. Invalid lines are ignored and reported on save.
- **REQ-SSR-008** — Disabled / not-in-store / not-yet-available products never appear (same rules as core `getProducts()`).
- **REQ-SSR-009** — Admin settings: status, strategies on/off (layout, translit, typo, synonyms), minimum query length, result cache
  TTL, "Rebuild index" button with progress/count and last-built time.
- **REQ-SSR-010** — The module off = core behaviour unchanged (SPEC-028 and core search work as before); no core file is edited.

## Non-functional requirements

- **REQ-SSR-020** — Per request: ONE indexed SQL query for the candidate products (token primary-key range/equality, no leading-wildcard `LIKE`, never
  `oc_product_description`); the phonetic/typo expansion reads small vocabulary lookups (by phonetic key, by term length) that are cached per index
  generation and runs the edit comparison in PHP, not in SQL.
- **REQ-SSR-021** — Expansion limits: <= 8 query variants, <= 40 expanded terms per word, <= 200 candidate product ids before ranking; query <= 100 chars.
- **REQ-SSR-022** — Index maintenance is incremental (admin product add/edit/delete events), never a full rebuild on a web request; full rebuild runs in
  batches (admin button with AJAX batches of 200 products and a CLI command).
- **REQ-SSR-023** — Result cache by normalised query (file cache, TTL from settings, invalidated by index generation counter); vocabulary cache
  rebuilt only on index generation change. Reuses the existing 10 requests / 10 s per IP limit.
- **REQ-SSR-024** — Target: < 100 ms server time per cache-miss request on 50k products / 300k tokens, < 10 ms on cache hit.
- **REQ-SSR-025** — PHP 7.4, MySQL/MariaDB, InnoDB; uk/ru/en admin and storefront strings; HTML-safe output (SPEC-001 REQ-ARCH-063).

## Data requirements

- `oc_ocs_search_token(token VARCHAR(48), product_id INT UNSIGNED, weight TINYINT UNSIGNED, PRIMARY KEY (token, product_id), KEY (product_id))`
  — normalised tokens AND phonetic keys of every field and language of a product (letters, digits, compact model forms; phonetic keys are NOT stored: they are computed for the query and matched through the vocabulary).
- `oc_ocs_search_vocab(term VARCHAR(48) PRIMARY KEY, phon VARCHAR(48), len TINYINT, df INT, KEY (phon), KEY (len, df))` — distinct letters-only terms of
  3+ characters with their phonetic key, for phonetic/typo expansion.
- Settings `module_ocs_super_search_*` (status, flags, min_length, limit, cache_ttl, synonyms, generation, built_at).
- New tables only (migration `custom/extensions/ocs_super_search/db/001_install.php`); no core table is altered (REQ-DB-030).

## Interfaces

- Admin: `extension/module/ocs_super_search` (settings + synonyms textarea + rebuild), route `…/rebuild` (batch JSON).
- Library `\Ocs\SuperSearch` (text helpers) and `\Ocs\SuperSearchIndex` (index, query). One catalog event pair (`getProducts/before`,
  `getTotalProducts/before`) serves both the search page and the AJAX suggestions (which call core `getProducts`), so `ocs_ajax_search` is unchanged.
- Admin events on product add/edit/delete keep the index current.
- CLI: `php scripts/search-reindex.php`.

## Edge cases

- Empty vocabulary / index not built -> consumers fall back to core search, admin sees a warning.
- Query of only digits -> digits match model/sku tokens exactly/prefix only (no fuzzy on numbers).
- Same word valid in two languages with different meaning -> both results returned, ranked by exactness.
- Very common term (df > 30% of products) -> not expanded fuzzily.
- Synonym cycles or a word in many groups -> expanded once, capped by REQ-SSR-021.

## Acceptance criteria

- [ ] `apple`, `aple`, `эпл`, `аппле`, `фззду` find the same product named "Apple …" in uk, ru and en storefront languages.
- [ ] `одиг` finds "одяг" products; `яблоко` finds a product whose only name is Ukrainian "яблуко" via phonetic/typo tiers.
- [ ] Synonym `одежда=шмотки` makes `шмотки` return "одежда" products and vice versa; editing the textarea applies without reindex.
- [ ] Disabling the module restores the old behaviour exactly.
- [ ] EXPLAIN of the candidate query uses the PRIMARY key range; benchmark meets REQ-SSR-024.
