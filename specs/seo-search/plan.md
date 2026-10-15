# PLAN — SPEC-013 SEO and Search

## Approach

Use SeoPro as shipped, constrain its settings by migration, add one extension `ocs_seo`
(meta templates, robots, JSON-LD, redirects, hreflang) and one feed extension `ocs_sitemap`.
Search starts on core LIKE with normalisation/synonyms/throttling; an indexed search is a later
spec triggered by measured latency.

## Decisions

| Decision | Choice | Why |
| -------- | ------ | --- |
| URL engine | SeoPro (bundled) | present in core, supports category paths and blog |
| Trailing slash | none, lower-case | one canonical form |
| Redirects | own table + not-found handler | slug edits happen in admin |
| Sitemap | own index feed | core feed is a single unbounded file |
| Search | staged | avoid premature infrastructure |
| Robots | generated per environment | prevent staging indexation |

## Affected files

`custom/extensions/ocs_seo/**`, `custom/extensions/ocs_sitemap/**`, `custom/seo/**`,
`custom/config/robots.txt.tpl`, migrations for settings/seo keywords/redirects.

## Risks

- SeoPro internals may intercept before our not-found handler -> verify event timing at implementation.
- Changing URL mode after launch is costly -> decision recorded once in store-config spec.
