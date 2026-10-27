# PLAN — SPEC-079 Super Search

## Approach

**Indexing.** On product save/delete (admin events `admin/model/catalog/product/{add,edit,delete}Product/after`) and on full rebuild, the
service collects text of all languages (name, tag, meta keywords), model, sku, brand, category names. Text is lowercased (`ё`->`е`), split to
tokens, and stored once; the **phonetic key** is kept only in the vocabulary row of letters-only terms. Phonetic key: transliterate Cyrillic (ru+uk) to Latin,
drop `h`/soft signs, map `c/k/q->k`, `y/i/ы/и/і->i`, `ts/ц->ts`, then collapse all vowels to a single class, collapse repeated letters and drop the final vowel
(`apple`, `аппле`, `эпл`, `aple` -> `apl`; `одяг`, `одиг` -> `adag`). Distinct terms go to `oc_ocs_search_vocab`.

**Query.** Normalise -> up to 8 variants (original; EN/RU/UK layout conversions). Per word: exact term + prefix range on `token` (prefix for
the last word), synonym groups (settings, parsed once and cached), phonetic key lookup, and fuzzy neighbours: the vocabulary rows of term length +-1
(cached per length and index generation) are scanned in PHP with Damerau-Levenshtein <= 1 limited to the bucket +-1, capped at 40 terms. All expanded terms of
all words go into ONE query `SELECT product_id, MAX(weight) … WHERE token IN (…) GROUP BY product_id HAVING words matched = N ORDER BY score LIMIT 200`
served by the `(token, product_id)` primary key. Product cards are then loaded through core model methods so store/status/price rules
stay core's; score order is preserved.

**Integration.** Verified in `core/upload/system/engine/loader.php`: a `model/.../before` event that returns a truthy value replaces the model call.
`catalog/model/catalog/product/getProducts/before` and `getTotalProducts/before` therefore return the ranked, filtered, sorted and paged products
(loaded with core `getProduct()`); an empty result or an unsupported filter (description, attribute filter, foreign tag text) leaves core search in
charge. The AJAX suggestions call core `getProducts` and are covered by the same hook; no OCMOD is needed.

**Admin.** Settings page like `ocs_ajax_search`; rebuild button uses batched AJAX; CLI script for cron/deploy.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Storage | InnoDB inverted token table | Indexed range lookups; `LIKE '%…%'` and `SOUNDEX`/Levenshtein in SQL scan everything |
| Fuzzy matching | PHP over cached vocabulary | Vocabulary is tens of thousands of terms, cheap; keeps SQL to one indexed query |
| Language independence | One index for all languages | No per-language row sets, no join on `language_id` |
| Packaging | New extension + library, consumers via events | `core/` read-only (SPEC-001); module off = no change |
| Synonyms | Expanded at query time | Edit applies instantly, no reindex |
| Phonetic collapse of vowels | Accept extra recall, rank it below exact | Needed for `apple`~`эпл`; ranking limits noise |

## Affected files / directories

- `custom/extensions/ocs_super_search/{extension.json,db/001_install.php,upload/admin/**,upload/catalog/**}`
- `custom/extensions/ocs_ajax_search/upload/catalog/controller/extension/module/ocs_ajax_search.php` (call the service)
- library `\Ocs\SuperSearch*` (location per SPEC-004 extension contract)
- `scripts/search-reindex.php`, PHP tests, `custom/VERSION`, `dist/` rebuilt via `npm run pack:theme`
- `specs/README.md` row

## Risks

- Phonetic key over-matching (noise) -> weights/ranking, df cut-off, key length >= 3, tune on demo catalogue tests.
- Index size on big catalogues -> tokens capped per product (200), vocabulary pruned of df=0 terms on rebuild.
- Price sort uses the active special or the base price; quantity discounts are not considered.
- DB code was never run on a real MySQL (project note) -> keep SQL simple; SQLite PHP tests plus a manual MySQL check task.
