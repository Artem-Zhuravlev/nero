# PLAN — SPEC-009 Quality Gates

## Approach

`verify.mjs` is a thin orchestrator over `checks/*.mjs` modules each exporting
`{ id, severity, run(ctx) -> Finding[] }`. PHP-aware rules call one PHP scanner script that emits
JSON findings from `token_get_all`. Fixtures drive tests (`node --test` for Node, plain PHP runner
for PHP).

## Decisions

| Decision | Choice | Why |
| -------- | ------ | --- |
| PHP analysis | tokenizer script, not regex | avoids matches in strings/comments |
| Warn budget | 20 | warnings cannot accumulate forever |
| Exemptions | file with owner+expiry | exemptions are visible and temporary |
| Core exemption | default | core is vanilla and read-only |
| Twig escape rule | name-based heuristics + safe prefixes | autoescape is off, so risk is real |

## Affected files

`scripts/verify.mjs`, `scripts/lib/checks/*`, `scripts/lib/php/scan.php`,
`scripts/test/fixtures/qa/**`, `verify.exemptions.json`, `.gitattributes`.

## Risks

- Heuristic rules (REQ-QA-021, 027, 030) produce false positives -> exemption path + tuning backlog.
- PHP CLI absent on a machine -> verify reports `php-lint` as an error, not skipped.
