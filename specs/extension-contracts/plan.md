# PLAN — SPEC-004 Extension Contracts

## Approach

Contracts are derived from core examples read in this repo: module `html`/`featured`, payment
`cod`, shipping `flat`, total `handling`, theme `default`. The scaffolder (`scripts/new-extension.mjs`)
holds the templates; the verify step (`scripts/lib/check-extensions.mjs`) enforces REQ-EXT-051.

## Decisions

| Decision | Choice | Why |
| -------- | ------ | --- |
| DB work location | migrations, not controller `install()` | reproducible from git; admin click not required |
| Declarations in `extension.json` | tables/settings/events/routes | machine-checkable cleanliness and clean uninstall |
| Event handlers location | `extension/<code>/events` | one predictable place, route-addressable |
| Library loading | `new \Ocs\X($registry)` preferred | avoids registry-key collisions |
| Vendored extensions | unchanged + OCMOD only | upgradeable, auditable |

## Affected files

`scripts/new-extension.mjs`, `scripts/lib/check-extensions.mjs`, `scripts/templates/extension/**`,
`custom/extensions/ocs_hello/**` (sample, becomes the reference implementation).

## Risks

- Per-type contracts copied from core may differ across core versions -> re-verify on upgrade.
- Payment `confirm()` flow depends on checkout JS in the default theme -> theme spec must keep it.
