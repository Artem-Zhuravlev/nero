# PLAN — SPEC-019 Performance and Availability

## Approach

Measure first (bench, query profiler, perf dataset), then apply layers that need no core edits: our InnoDB tables, event-based short-circuits (`updateViewed`), full-page cache
extension for anonymous traffic, asset versioning/critical CSS in the theme, server runtime profile documented. Core table engine conversion stays a separate, risk-reviewed spec.

## Decisions

| Decision | Choice | Why |
| -------- | ------ | --- |
| FPC | extension using controller `before` short-circuit, web-server hook as fallback | no core edit; measure benefit |
| View counters | batch via cache + job | removes MyISAM write lock per view |
| Core table engines | not changed by default | high risk, FULLTEXT/locking semantics |
| Load tool | in-repo Node script | no external deps |
| Budgets | JSON in git | enforced at release |

## Affected files

`custom/perf/**`, `scripts/perf-*.mjs`, `scripts/seed-perf.php`, `custom/extensions/ocs_fpc/**`, `custom/extensions/ocs_images/**`, `docs/{runtime-profile,capacity,perf-log}.md`,
`docs/runbooks/myisam-to-innodb.md`.

## Risks

- Event timing may not skip enough pre-action work for FPC to matter; mitigation documented (web server level hook).
- FPC bugs risk showing one customer's data to another; strict bypass rules + tests with cookies/sessions.
