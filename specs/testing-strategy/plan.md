# PLAN — SPEC-021 Testing Strategy

## Approach

Build the smallest harness that gives confidence: a PHP runner with stubs (L1), a disposable MySQL schema with snapshot/restore (L2), a Node HTTP scenario runner with cookie jar and simulators (L3/L4).
Requirement-level traceability matters more than coverage numbers.

## Decisions

| Decision | Choice | Why |
| -------- | ------ | --- |
| Framework | own tiny runner | no Composer/PHPUnit in this repo (SPEC-001 REQ-ARCH-004); easy to run everywhere |
| DB isolation | per-class snapshot restore | MyISAM has no transactions to roll back |
| HTTP tests | JSON scenarios | readable, diffable, runnable by agents |
| Clock | injected | determinism without sleeps |
| Test support in builds | opt-in extension | never ships to production |

## Affected files

`scripts/run-php-tests.php`, `scripts/test/php/**`, `scripts/test-db.php`, `scripts/run-http-tests.mjs`, `custom/tests/**`, `custom/extensions/ocs_test_support/**`, `.env.test.example`.

## Risks

- Writing our own runner costs time; mitigated by keeping it < 600 lines and well tested.
- L2 snapshot restore speed on MyISAM depends on data size; seed dataset kept small.
