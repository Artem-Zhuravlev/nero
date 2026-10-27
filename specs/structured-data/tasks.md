# SPEC-080 Tasks

| ID        | Task                                                                                                   | Requirements                        | Check |
| --------- | ------------------------------------------------------------------------------------------------------ | ----------------------------------- | ----- |
| T-SD-001  | `Ocs\StructuredData`: sanitisers (`text`, `url`, `price`, `date`, enums), `graph()` with drop-incomplete + safe encode | REQ-SD-001, 010, 012      | TEST-SD-001 hostile strings -> valid JSON, no `<>&`, `</`; TEST-SD-002 URL/price/date/currency rules |
| T-SD-002  | Node builders: organization, website, page, breadcrumbs, itemList                                       | REQ-SD-002, 003, 006, 008           | TEST-SD-003 required props, `@id` uniqueness, positions across pages |
| T-SD-003  | Product builder (offer, availability, priceValidUntil, gtin/mpn, images) + article builder              | REQ-SD-004, 007                     | TEST-SD-004 product/offer/article required props, hidden price, pre-order |
| T-SD-004  | Reviews: average, `aggregateRating`, `Review[]` (≤ 10) with data from the review model                  | REQ-SD-005, 011                     | TEST-SD-005 rating maths, none without reviews |
| T-SD-005  | Event `structuredData` + route map; remove old `ProductExtras::schema` and `ocs_px.schema` from `product.twig` | REQ-SD-001, 006, 008, 011  | TEST-SD-006 static: one injection point, AJAX/partial guard |
| T-SD-006  | Migration 0011: three settings + event registration; admin form field; language strings (ru-ru, uk-ua, en-gb) | REQ-SD-009                    | TEST-SD-007 migration idempotent (php runner), strings in all 3 languages |
| T-SD-007  | Remove microdata breadcrumbs from all templates; update `breadcrumbs.test.mjs`                          | REQ-SD-003                          | TEST-SD-008 no `itemscope`/`itemprop` in `custom/**/*.twig` |
| T-SD-008  | Docs (`ocstore-reference.md`), `specs/README.md` row                              | REQ-SD-013                          | `npm run verify` |
| T-SD-009  | `npm run test:php`, `npm test`, `npm run verify`, `npm run pack:theme`; status -> `implemented`          | all                                 | all green, archive rebuilt |
| T-SD-010  | Manual (user): Rich Results Test + validator.schema.org on product, category, article, information, contact, home | acceptance                  | recorded in spec |
