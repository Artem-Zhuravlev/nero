# SPEC-007 — OCMOD and Event Policy

**Status:** draft
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-15
**Refines:** SPEC-001 section 4, REQ-ARCH-021/022/101/102 · **Facts:** [`docs/ocstore-internals.md`](../../docs/ocstore-internals.md) §4, §9; [`docs/ocstore-reference.md`](../../docs/ocstore-reference.md) §7

## Problem

Events and OCMOD are the two ways to change core behaviour without editing `core/`. OCMOD
patches are string replacements on core source: fragile, silent when they stop matching, and
invisible to PHP linting. Events are safer but their registry (`oc_event`, MyISAM) fails
silently on wrong triggers. We need strict rules on when each is allowed and how it is
authored, tested and tracked.

## Purpose

Specify the decision rules, file formats, naming, testing and upgrade-tracking for OCMOD files
and event handlers.

## Scope

`custom/ocmod/*.ocmod.xml`, event registration/handlers, registry document of core
touch-points, verification and upgrade reports.

## Out of scope

Third-party OCMOD packages management beyond REQ-EXT-071.

## Functional requirements

### Choosing the mechanism

- **REQ-OCM-001** — Allowed use of **events** (preferred): add/alter template variables
  (`view/<route>/before`), post-process HTML (`view/<route>/after`), pre/post-process model
  calls (`model/<route>/before|after`), intercept controllers (`controller/<route>/before|after`),
  override language strings (`language/<route>/after`), add admin menu items
  (`admin/view/common/column_left/before`).
- **REQ-OCM-002** — **OCMOD only when** an event cannot express the change: altering SQL inside
  a core model, changing logic *inside* a core controller method, adding fields to a core admin
  form or core template that is not overridable via theme (admin templates), changing a PHP
  constant/default in core. Each OCMOD documents in its header why an event was insufficient.
- **REQ-OCM-003** — OCMOD never touches: `system/startup.php`, `system/framework.php`,
  `system/modification.xml`'s own targets (`system/engine/{action,loader,config,language}*.php`,
  `system/library/template/*.php`), any file under `core/.../vendor/`, or `install/`. Verify
  rejects such targets.

### Event authoring

- **REQ-OCM-010** — Registration only via `$m->event->register` in migrations (REQ-DB-023). Event
  `code` format `ocs_<ext>_<what>`, unique, one row per trigger.
- **REQ-OCM-011** — Trigger grammar: `<app>/<kind>/<route>[/<method>]/<phase>` where
  app ∈ {catalog, admin}, kind ∈ {controller, model, view, language}, phase ∈ {before, after};
  for `controller`/`view`/`language` there is **no method**, for `model` the method is the last
  segment before the phase (`catalog/model/checkout/order/addOrderHistory/after`). Wildcards `*`/`?`
  only in `controller/*` and `view/*` forms and justified in the extension docs.
- **REQ-OCM-012** — Handler signatures (by-reference parameters mandatory):
  controller `(&$route, &$data)` before / `(&$route, &$data, &$output)` after;
  model `(&$route, &$args)` before / `(&$route, &$args, &$output)` after;
  view `(&$route, &$data, &$code)` before / `(&$route, &$data, &$output)` after;
  language `(&$route, &$key)` before / `(&$route, &$key, &$output)` after.
- **REQ-OCM-013** — A `before` handler returns `null` unless it intends to replace the original
  call (documented in the handler docblock). Returning non-null from `before` short-circuits
  core and all later `before` handlers.
- **REQ-OCM-014** — Ordering: `sort_order` ascending; core handlers have 0/500/998-ish values in
  config events; our handlers use 100–899 and never 0, 500, 998, 999, 1000.
- **REQ-OCM-015** — Event handlers are idempotent per request and do not perform I/O (HTTP,
  mail, file writes) inside `view` events; heavy work goes in `model`/`controller` `after` events
  with try/catch and logging (REQ-ARCH-121).
- **REQ-OCM-016** — Each trigger used by us is listed in `docs/event-registry.md` (trigger,
  extension, purpose, owner spec). Verify compares the registry to migrations.

### OCMOD authoring

- **REQ-OCM-020** — File name `custom/ocmod/ocs_<concern>.ocmod.xml`; root `<modification>` with
  `<name>`, `<code>` (equals file stem), `<version>`, `<author>`, `<link>`. Encoding UTF-8, LF.
- **REQ-OCM-021** — Header XML comment: purpose, owning SPEC, "event insufficient because…",
  `@core-file`/`@core-version` for **each** `<file>` target (REQ-ARCH-101).
- **REQ-OCM-022** — Each `<operation>` uses the shortest **unique** `<search>`; `index` is not used
  to disambiguate (fragile) unless commented; `regex="true"` only with a comment explaining the
  pattern; `error` attribute default (log) except `error="abort"` for operations whose absence
  would break the store.
- **REQ-OCM-023** — Allowed `<add position>` values: `before`, `after`, `replace`, `ibefore`,
  `iafter`; `top`/`bottom` forbidden for PHP files (they break `<?php` structure).
- **REQ-OCM-024** — Replaced/added PHP must be syntactically valid; verify applies each OCMOD to
  the **pristine** core file in a temp dir (using a reference implementation of the OCMOD
  algorithm in `scripts/lib/ocmod-apply.mjs`) and runs `php -l` on the result.
- **REQ-OCM-025** — OCMOD files are installed by migration (`$m->ocmod->install`) and activated
  (`status=1`); order between OCMODs is by `modification_id`; two OCMODs must not target the same
  lines (verify detects overlapping search anchors).
- **REQ-OCM-026** — After install/update, `scripts/refresh.php` must run; CI-style check: the
  generated file under `DIR_MODIFICATION` contains each added snippet.

### Forbidden / restricted

- **REQ-OCM-040** — OCMOD on theme templates (`catalog/view/theme/**`) is forbidden: override via
  theme (SPEC-006) or inject via view event. (Reason: `event/theme` prefers `DIR_MODIFICATION`
  copies.)
- **REQ-OCM-041** — Admin templates (`admin/view/template/**`) may be patched by OCMOD (no theme
  mechanism exists); keep patches additive (`after`/`before`).
- **REQ-OCM-042** — Core language files are never patched; use language events or `oc_translation`.
- **REQ-OCM-043** — vQmod and manual edits are forbidden (REQ-ARCH-022).

### Upgrade tracking

- **REQ-OCM-050** — `node scripts/core-impact.mjs` lists, for the current `core/` hash manifest vs a
  previous one (`--from <manifest>`), every `@core-file` dependent (OCMOD, event handler,
  theme override, overlay file) whose target file changed, and re-applies all OCMODs to the new
  core in a temp dir reporting failed anchors (REQ-OCM-024).
- **REQ-OCM-051** — `docs/core-touchpoints.md` is generated by that script (not hand-edited): one
  table of core files touched by us and by what.

## Non-functional requirements

- **REQ-OCM-060** — Total number of active OCMOD files is minimised; adding the 11th requires a
  design review note in the spec (soft limit).
- **REQ-OCM-061** — Every event handler and OCMOD has at least one automated check: unit test of the
  handler logic, or a build-time apply test (OCMOD).

## Data requirements

Rows in `oc_event` and `oc_modification` created only by migrations.

## Edge cases

- `search` found multiple times -> core replaces **all** (unless `index`); verify warns when an
  anchor matches more than once in the pristine file.
- Two extensions add admin menu entries via `column_left/before` -> both appear; order by
  `sort_order`.
- A `view` event removes/adds keys in `$data` that the template iterates -> must keep types stable
  (arrays stay arrays).
- Refresh not run after migration -> production shows old behaviour; `migrate` prints
  "refresh required" and `status` marks modifications "dirty" until `refresh.php` succeeds.

## Acceptance criteria

- [ ] Sample event (`view/common/header/before` adds `asset_version`) works and is in registry doc.
- [ ] Sample OCMOD applies to pristine core, `php -l` passes, refresh produces the patched file.
- [ ] Verify fails for: forbidden target, `top` on PHP, OCMOD on a theme template, unregistered
      trigger, handler without by-reference signature.
- [ ] `core-impact` reports an intentionally changed core file's dependents (fixture test).
