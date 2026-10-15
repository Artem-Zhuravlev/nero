# PLAN — SPEC-022 Multi-store and Environments

## Approach

Treat stores and environment differences as data: `stores.json`, `allowed-differences.json`, `.env`. Provide tooling for export/diff/sanitise so drift is detected mechanically, and keep code store-agnostic through
config-driven access.

## Decisions

| Decision | Choice | Why |
| -------- | ------ | --- |
| Store definition | JSON + migration with explicit ids | reproducible ids across environments |
| Env differences | whitelist | prevents "works on staging" drift |
| Staging data | sanitised copy of production | realistic but safe |
| Direction of data | prod → staging only | avoids test data in production |
| Per-store permissions | not in v1 | core has none; separate spec if needed |

## Affected files

`custom/stores/*`, `custom/env/*`, `scripts/{env-diff,settings-export,sanitize-staging}.php`, migration `NNNN_stores`, theme staging banner event.

## Risks

- Explicit `store_id` inserts into `oc_store` must account for MyISAM auto-increment.
- Secret-key masking heuristics may miss odd key names → allow-list approach for exports of known-safe settings.
