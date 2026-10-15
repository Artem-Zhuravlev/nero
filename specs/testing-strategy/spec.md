# SPEC-021 — Testing Strategy and Harness

**Status:** draft
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-15
**Refines:** SPEC-001 REQ-ARCH-150..152, SPEC-009 · Tooling contract only; no framework is installed yet.

## Platform facts (verified in `core/upload/`)

1. No test suite ships with ocStore; no PHPUnit, no Composer app. `system/storage/vendor/` carries Twig/ScssPhp autoload only (reference §8).
2. Code relies on a **Registry** passed to constructors (`$registry->get('db'|'config'|'cache'|'log'|…)`), so classes taking `$registry` can be tested with a stub registry
   without booting the framework (`system/engine/registry.php` is a small key/value class).
3. The `DB` class (`system/library/db.php`) wraps a driver (`mysqli`); queries return an object with `row`, `rows`, `num_rows`; `escape()`, `getLastId()`, `countAffected()`.
4. Controllers read `$this->request`, `$this->session`, `$this->load`, `$this->document`, `$this->response` — heavy to stub; they are kept thin by SPEC-001 REQ-ARCH-031.
5. HTTP behaviour (routing, SEO URLs, events, OCMOD) only exists in a running store built by SPEC-003.

## Problem

Money-handling, SEO and security code needs automated checks, but the platform offers no harness and the team has no Composer pipeline. Tests must be cheap enough to run on every task and
honest about what they cover.

## Purpose

Define the test pyramid for this repository, the in-repo PHP and Node harness, fixtures, naming, DB-backed integration tests with isolated schemas, HTTP/E2E scenarios, coverage expectations and
how tests map to requirements.

## Scope

Harness design, test types and locations, stubs/fakes, DB test lifecycle, HTTP scenario runner, fixtures, determinism, reporting, requirement traceability, CI-readiness.

## Out of scope

Choosing a CI service; browser-based visual regression (listed as future work); load tests (SPEC-019).

## Functional requirements

### Pyramid and locations

- **REQ-TST-001** — Levels: **L0 static** (SPEC-009 checks), **L1 unit** (pure PHP/Node logic, no DB, no HTTP), **L2 integration** (PHP with a real MySQL/MariaDB test schema, migrations applied),
  **L3 HTTP** (running build, requests only), **L4 scenario** (multi-step flows: checkout, webhook, account). Each requirement names its level in `tasks.md` (`TEST-… (L1)`).
- **REQ-TST-002** — Locations: PHP tests in `custom/extensions/<code>/tests/` (L1/L2) and `custom/tests/` (cross-extension); Node tests in `scripts/test/`; HTTP/scenario specs in
  `custom/tests/http/*.json` and `scripts/scenario-*.mjs`; fixtures in `…/fixtures/`. Tests are never copied into the build output (build excludes any `tests/` directory).
- **REQ-TST-003** — Naming: file `<Subject>Test.php`; test method `test_<behaviour>`; docblock/comment `TEST-<AREA>-<NNN> — verifies REQ-<AREA>-<NNN>` (SPEC-009 REQ-QA-041). One requirement
  may have several tests; one test verifies at most three requirements.

### PHP harness (`scripts/test/php/`)

- **REQ-TST-010** — Dependency-free runner `php scripts/run-php-tests.php [--filter x] [--level L1|L2] [--junit out.xml]`: discovers `*Test.php`, provides assertion helpers (`assertSame`, `assertTrue`,
  `assertArrayHas`, `assertThrows`, `assertSql`-style helpers for query strings, `assertJsonEquals`), setUp/tearDown, data providers via `provide_*` methods, per-test timeout, colourised summary, exit code
  non-zero on failure, JUnit XML output for CI.
- **REQ-TST-011** — Stubs in `scripts/test/php/Stub/`: `StubRegistry` (set/get/has, throws on unknown key), `FakeConfig` (array-backed `get/set/has`), `FakeCache` (array with TTL), `FakeLog` (collects lines),
  `FakeLanguage` (returns key unless set), `FakeSession`, `FakeRequest` (get/post/server/cookie arrays), `FakeResponse` (captures output/headers/redirect), `FakeUrl` (`link()` returns `route?args`),
  `RecordingDb` (records queries, returns queued results — L1 only), `FakeHttp` (queued responses for `Ocs\Http\Client`, SPEC-018).
- **REQ-TST-012** — Libraries under test are constructed with `new \Ocs\X(new StubRegistry([...]))`; the runner registers the same autoload rule as core (`Ocs\X` → `system/library/ocs/x.php` from the
  extension's `upload/` tree and from `core/upload/system/library/`) so no framework boot is required.
- **REQ-TST-013** — Controller smoke tests (optional, L1) boot a controller with stubs through a helper `ControllerHarness::make('catalog/controller/extension/…', $registry)`; allowed only for thin controllers and
  they assert data passed to `load->view`, not HTML.
- **REQ-TST-014** — Tests never use `sleep`; time is injected (`$clock` closure in libraries that need `time()`) and `FakeClock` is provided.

### Database integration (L2)

- **REQ-TST-020** — L2 uses a dedicated schema `<DB_NAME>_test` created by `scripts/test-db.php create` on the local server: installs the core schema by running `core/upload/install/opencart.sql`
  with prefix `oc_` (the same replace logic as the installer), creates the admin user, applies all migrations (SPEC-005) — then **snapshots** it via `CREATE TABLE … LIKE` copies in a `_base` set of tables or
  a dump file in `var/test/base.sql.gz`.
- **REQ-TST-021** — Each L2 test class starts from the snapshot (fast restore: `TRUNCATE`+`INSERT … SELECT` for touched tables, or reload of the dump for the class) and cleans up afterward; tests are order-independent
  and may run in parallel only on different schemas (`<DB_NAME>_test_<worker>`).
- **REQ-TST-022** — L2 refuses to run when the configured DB host is not local or the schema name does not end with `_test*`; production-like credentials are never read (the test runner reads its own
  `.env.test`).
- **REQ-TST-023** — Migration tests: apply all migrations on an empty installed schema, run `up` twice (no change), simulate failure at each `.php` migration step (fixture hooks) and re-run; compare
  `SHOW CREATE TABLE` snapshot (SPEC-005 REQ-DB-061).
- **REQ-TST-024** — Model tests run SQL through the real `DB` class against fixtures built by builders (`Fixture\Product::create([...])`), asserting rows via `SELECT`, not via the model under test.
- **REQ-TST-025** — Core admin models used by catalogue import (AdminBoot, SPEC-012) are exercised in L2 with a small fixture catalogue.

### HTTP and scenarios (L3/L4)

- **REQ-TST-030** — `node scripts/run-http-tests.mjs --base-url <url>` executes JSON test files: steps `{request: {method, path, headers, form|json, cookies}, expect: {status, headers, bodyContains[], bodyNotContains[],
  jsonPath: {}, cookieSet[], cookieAbsent[], redirectTo}, capture: {name: regex|jsonPath}}`; captured values feed later steps (CSRF-less core, so mainly session cookies and ids); a cookie jar is kept per scenario.
- **REQ-TST-031** — Core scenarios shipped in `custom/tests/http/`: `smoke.json` (SPEC-009 REQ-QA-060), `checkout-guest.json` (SPEC-011 REQ-ORD-061), `account-register-login.json`, `seo-urls.json`
  (canonical/redirect/robots/sitemap), `consent-no-third-party.json` (SPEC-017), `webhook-payment.json` (against simulator, SPEC-018), `fpc.json` (SPEC-019).
- **REQ-TST-032** — Scenarios run against a **seeded** local store created by a deterministic seed (`custom/db/seed/`, fixed ids/timestamps via `--freeze-time`), and against simulators for providers;
  they never contact real third parties.
- **REQ-TST-033** — Time-dependent behaviour (coupon dates, specials, token expiry) is testable via a `ocs_test_clock` setting honoured only when `APP_ENV=local` and the test extension `ocs_test_support` is
  installed (never in production builds: the extension is excluded unless `INCLUDE_TEST_SUPPORT=1`).
- **REQ-TST-034** — Mail is verified through the local SMTP sink (SPEC-014 REQ-OPS-021): scenarios fetch `.eml` files from `var/mail/` and assert subject/recipient/body markers.
- **REQ-TST-035** — Browser-level tests (JS behaviours: consent banner, cart AJAX, pickup point widget) are optional L4 using the Browser pane tooling manually or a headless runner added later; until then each such
  feature lists a manual test script in its spec with expected results.

### Determinism, speed, reporting

- **REQ-TST-040** — Tests are deterministic: fixed seeds for random data (`mt_srand`), no dependency on current date (use clock), no network, no reliance on test order.
- **REQ-TST-041** — Budgets: L1 suite < 20 s, L2 < 3 min, L3/L4 < 5 min on the reference machine; slower tests are tagged `@slow` and run in the release gate only.
- **REQ-TST-042** — `npm run test`, `test:unit`, `test:integration`, `test:http` map to the runners; `verify` runs L0+L1 always, L2 when a DB is configured, L3/L4 with `--smoke`.
- **REQ-TST-043** — Reports: console summary + `var/test/report.json` (id, level, REQ ids, status, duration); the traceability check (SPEC-009) reads it to confirm every `implemented` requirement has a passing test.
- **REQ-TST-044** — Flaky tests are quarantined within 24 h by `@quarantine <reason> <expiry>` and excluded from the gate; quarantine entries expire like verify exemptions (SPEC-009 REQ-QA-004).

### What must be tested (minimum coverage rules)

- **REQ-TST-050** — Every library in `system/library/ocs/` has L1 tests for each public method (happy path, boundary, invalid input); money/tax/total/status-transition code additionally has property-style tests
  (random inputs, invariants such as "sum of lines = total").
- **REQ-TST-051** — Every migration `.php` has an L2 idempotence test; every helper in `Ocs\Migrate` has L1/L2 tests (SPEC-005 acceptance).
- **REQ-TST-052** — Every event handler has an L1 test of its logic plus one L3/L4 assertion that it fires (via observable output).
- **REQ-TST-053** — Every payment/shipping/total extension has L1 tests of `getMethod/getQuote/getTotal` return shapes (SPEC-004) and an L4 scenario with its simulator.
- **REQ-TST-054** — Security-sensitive code (signature checks, throttles, sanitiser, permission checks) has negative tests for each documented attack/failure case.
- **REQ-TST-055** — Coverage is tracked by requirement, not line percentage; a line-coverage report via Xdebug/PCOV is optional and informational (target ≥ 80 % for `system/library/ocs/`).

### Test data and builders

- **REQ-TST-060** — Fixture builders in `scripts/test/php/Fixture/` create minimal valid entities (product with description in all enabled languages, category, customer, order with lines) through SQL
  helpers that respect core table shapes (reference §9, §11) and return ids; defaults are valid, tests override only what they assert on.
- **REQ-TST-061** — A deterministic demo/seed dataset for scenarios is defined in `custom/tests/seed.json`, applied by the seed tool; ids are stable so scenario files can reference them.

## Non-functional requirements

- **REQ-TST-070** — The harness has zero third-party dependencies and runs on Windows, Linux and macOS with PHP ≥ 7.3 and Node ≥ 20.
- **REQ-TST-071** — Failing output points to `file:line` and shows expected vs actual with a diff for strings/arrays.

## Data requirements

`.env.test`, `var/test/*`, `custom/tests/**`.

## Edge cases

- Test DB left dirty by an aborted run → `test-db.php create --force` recreates; runner detects and refuses to reuse a dirty marker table.
- Parallel workers collide on ports/schemas → worker id in schema names and port ranges.
- Core upgrade changes `opencart.sql` → base snapshot rebuilt automatically when the SQL file hash changes.
- PHP version differences (8.x deprecations) → runner sets `error_reporting` like the generated config; deprecations from core are filtered, deprecations from custom code fail tests.

## Acceptance criteria

- [ ] Runner executes a sample L1 test of a stub library and reports pass/fail with correct exit codes.
- [ ] L2 base snapshot builds from `opencart.sql` + migrations and restores in < 5 s per class.
- [ ] HTTP runner runs `smoke.json` against a local build and fails on a deliberately wrong expectation.
- [ ] Traceability check consumes `report.json` and flags a requirement with no passing test.
