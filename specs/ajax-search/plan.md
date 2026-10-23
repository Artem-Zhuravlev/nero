# PLAN — SPEC-028 AJAX Search

## Approach

Catalog controller returns JSON via the core product model with a filter on name/model/sku; a thin cache wraps repeated
queries. JS (`OCS.search`) renders the dropdown and stores history; admin edits popular queries.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Engine | MySQL LIKE + indexes | Third-party search engines are out of scope |
| History | localStorage | No server data about visitors |

## Affected files / directories

- `custom/extensions/ocs_ajax_search/`, migration for indexes.

## Risks

- LIKE on large catalogues -> prefix match, limits and cache; engine integration left to SPEC-018.
