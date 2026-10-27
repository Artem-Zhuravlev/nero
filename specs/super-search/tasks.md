# TASKS — SPEC-079 Super Search

| Task         | Description                                                              | Fulfills                             | Check        | Status  |
| ------------ | ------------------------------------------------------------------------ | ------------------------------------ | ------------ | ------- |
| TASK-SSR-001 | Normalizer: lowercase, tokenizer, layout converter EN/RU/UK              | REQ-SSR-002, 005                     | TEST-SSR-001 | done |
| TASK-SSR-002 | Phonetic key (translit + vowel/double collapse)                          | REQ-SSR-003                          | TEST-SSR-002 | done |
| TASK-SSR-003 | Fuzzy matcher (Damerau-Levenshtein <= 1, buckets, caps)                  | REQ-SSR-004, 021                     | TEST-SSR-003 | done |
| TASK-SSR-004 | Synonym parser + expansion                                               | REQ-SSR-007                          | TEST-SSR-004 | done |
| TASK-SSR-005 | Migration: token + vocab tables, settings, permission, admin events      | REQ-SSR-010, 022                     | TEST-SSR-005 | done |
| TASK-SSR-006 | Indexer: per-product update/delete, batch rebuild, CLI                   | REQ-SSR-001, 022                     | TEST-SSR-006 | done |
| TASK-SSR-007 | Search service: one-query candidate fetch, ranking, caches, rate limit   | REQ-SSR-005, 006, 008, 020, 023, 024 | TEST-SSR-007 | done |
| TASK-SSR-008 | Admin page (settings, synonyms textarea, rebuild, warnings) uk/ru/en     | REQ-SSR-007, 009, 025                | TEST-SSR-008 | done |
| TASK-SSR-009 | Hook into ocs_ajax_search and the search page (event or OCMOD), fallback | REQ-SSR-001, 010                     | TEST-SSR-009 | done |
| TASK-SSR-010 | Acceptance tests on demo catalogue + EXPLAIN/benchmark script            | REQ-SSR-020, 024                     | TEST-SSR-010 | done |
| TASK-SSR-011 | `npm run verify`, `npm run pack:theme`, README row, status `implemented` | all                                  | TEST-SSR-011 | done |
