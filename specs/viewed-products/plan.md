# PLAN — SPEC-030 Recently Viewed

## Approach

A catalog event after `product/product` records the view; the block is a module rendered through layouts. Guest history in a
cookie of product ids (max 20) read server-side; logged-in history in a table.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Guest storage | Cookie | Server needs ids to render the block without JS |

## Affected files / directories

- `custom/extensions/ocs_viewed/`, migration for `oc_ocs_viewed`.

## Risks

- Full-page cache (SPEC-019) would freeze the block -> block loads via small AJAX call when cache is enabled.
