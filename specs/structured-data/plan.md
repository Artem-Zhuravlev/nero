# SPEC-080 Plan — Structured Data

## Approach

JSON-LD (Google's preferred format) generated server-side by one pure-PHP library; one `catalog/view/*/after` event appends the
script before `</head>` of full-page output. Per-route builders read the data the controllers already put into `$data`
(`products`, `breadcrumbs`, `heading_title`, `thumb`, `articles`…), so templates stay untouched except for removing microdata.

## Decisions

- **Library first, event thin** (coding-rules: thin controllers): `system/library/ocs/structureddata.php` has no framework deps;
  builders take plain arrays, return arrays; `graph()` drops incomplete nodes and encodes. Fully unit-testable by the dependency-free
  PHP runner (`scripts/test/php/`).
- **After-event, not template edits**: the page's own `$data` is available there (`Loader::view` passes `&$data, &$output`), the
  header is already rendered into `$output`, so one `str_replace` on the first `</head>` inserts the script. Guard: route is in the
  map, output contains `</head>`, not an AJAX request, setting on. Avoids touching 44 templates for output.
- **Breadcrumb microdata removal** is a mechanical edit of the same 44 templates + `ocs_category_wall_page.twig`; `breadcrumbs.test.mjs`
  is updated to assert "no microdata, current item is `<span aria-current>`".
- **Product data**: the event loads the product row itself (independent of the `ocs_product_extras` switch); gallery images come from
  the view data. Reviews: `model_catalog_review->getReviewsByProductId($id, 0, 10)`; special end date from
  `product_special`. Exact approach fixed in T-SD-004.
- **`Organization`** is built once per request from settings + `Ocs\Contacts::view` (SPEC-047).

## Affected files

- new: `custom/extensions/ocs/upload/system/library/ocs/structureddata.php`
- edit: `custom/extensions/ocs/upload/catalog/controller/extension/ocs/events.php` (new `structuredData` method)
- edit: `custom/extensions/ocs_product_extras/.../productextras.php` and `events.php` (remove old `schema()`), `product.twig`
- edit: ~45 twig templates (microdata removal), `custom/extensions/ocs_category_wall/.../ocs_category_wall_page.twig`
- new: `custom/db/migrations/0011_structured_data_settings.php`; admin theme form field + language strings (ru-ru, uk-ua, en-gb)
- new tests: `scripts/test/php/structureddata_test.php`, `scripts/test/structured-data.test.mjs`; update `breadcrumbs.test.mjs`, `seo.test.mjs`
- docs: `docs/ocstore-reference.md` (setting keys, event), `specs/README.md` row, `custom/VERSION` bump, rebuilt `dist/*.zip`

## Risks

- Event runs on every view render: keep the route-map lookup first and cheap.
- OCMOD/SeoPro changing URLs after our event: we read URLs from `$this->url->link`, which SeoPro already rewrites.
- Rich-result eligibility cannot be proven offline; mitigated by required-property tests and the manual validator step.
