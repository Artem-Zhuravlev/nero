# SPEC-004 — Extension Contracts

**Status:** approved
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-15
**Refines:** SPEC-001 sections 4, 5, 6 · **Facts:** [`docs/ocstore-reference.md`](../../docs/ocstore-reference.md) §3–§5

## Problem

Every feature of the store will be an extension. Without an exact contract (files, class names,
setting keys, return shapes, install steps, tests) each extension is built differently and breaks
core flows such as checkout.

## Purpose

Define, per extension type, the mandatory structure and behaviour, the scaffolder that produces
it, and the rules that keep extensions independent, removable and testable.

## Scope

Extension manifest, naming, file layout, per-type contracts (module, payment, shipping, total,
theme, dashboard/report/feed), settings, install/uninstall via migrations, libraries, events used
by extensions, versioning, dependencies, vendored third-party extensions.

## Out of scope

Concrete business extensions (own specs), ocmod format (SPEC-007), theme internals (SPEC-006).

## Functional requirements

### Identity and manifest

- **REQ-EXT-001** — Extension code: `ocs_` + snake_case (`^ocs_[a-z][a-z0-9_]{1,30}$`), unique
  across `custom/extensions/`. The directory name equals the code. The only exception is the
  theme extension, whose code is the bare theme name `ocs` (SPEC-006 REQ-THM-001). Codes are
  at most 32 characters (`oc_extension.code` is `varchar(32)`).
- **REQ-EXT-002** — `extension.json` is mandatory:
  ```json
  { "code": "ocs_featured_plus", "type": "module", "version": "1.0.0",
    "title": { "en-gb": "Featured Plus", "uk-ua": "…" },
    "requires": { "core": "3.0.3.7", "extensions": [] },
    "spec": "SPEC-0NN", "tables": ["oc_ocs_featured_plus_item"],
    "settings": ["module_ocs_featured_plus_status"],
    "events": ["ocs_featured_plus_after_add_order"],
    "routes": ["extension/module/ocs_featured_plus"] }
  ```
  `type` ∈ `module|payment|shipping|total|theme|dashboard|report|feed|captcha|currency|fraud|analytics|advertise|library`.
  `library` = no admin presence, code only (system/library + events).
  `spec` links the extension to the spec that owns it. `tables/settings/events/routes` are
  declarations: verify checks that migrations create exactly these and nothing else (REQ-EXT-050).
- **REQ-EXT-003** — Semantic version `MAJOR.MINOR.PATCH`; any change to behaviour bumps it in the
  same commit as the change; the version is shown in the admin form footer and recorded in
  `BUILD_INFO.json`.
- **REQ-EXT-004** — `requires.extensions` lists codes that must be present; build orders
  migrations accordingly and fails on cycles or missing requirements.

### File layout

- **REQ-EXT-010** — Layout is exactly the one in SPEC-001 §5 plus `tests/` (REQ-ARCH-150) and
  `db/` (migrations belonging to the extension, REQ-DB-020). Nothing else at the extension root.
- **REQ-EXT-011** — Files per type (✓ mandatory, ○ optional):

  | File (under `upload/`) | module | payment | shipping | total | theme | dashboard |
  |---|:-:|:-:|:-:|:-:|:-:|:-:|
  | `admin/controller/extension/<type>/<code>.php` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
  | `admin/language/<lang>/extension/<type>/<code>.php` (each lang) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
  | `admin/view/template/extension/<type>/<code>.twig` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
  | `admin/model/extension/<type>/<code>.php` | ○ | ○ | ○ | ○ | ○ | ○ |
  | `catalog/controller/extension/<type>/<code>.php` | ✓ | ✓ | ○ | ○ | – | – |
  | `catalog/model/extension/<type>/<code>.php` | ○ | ✓ | ✓ | ✓ | – | – |
  | `catalog/language/<lang>/extension/<type>/<code>.php` | ✓ | ✓ | ✓ | ✓ | ○ | – |
  | `catalog/view/theme/default/template/extension/<type>/<code>.twig` | ✓ | ✓ | – | – | – | – |
  | `system/library/ocs/<name>.php` | ○ | ○ | ○ | ○ | ○ | ○ |

  Dashboard extensions additionally need the catalog-less admin widget twig (`<code>_info`
  pattern in core `admin/controller/extension/dashboard/*.php`) — copy the closest core one.
- **REQ-EXT-012** — Every file begins with a header comment: extension code, version-independent
  purpose, owning `SPEC-NNN`, and (if it depends on a core file) `@core-file`.
- **REQ-EXT-013** — Class and file naming follow the route: route `extension/payment/ocs_bank`
  -> file `…/extension/payment/ocs_bank.php` -> class `ControllerExtensionPaymentOcsBank`
  (admin & catalog), models `ModelExtensionPaymentOcsBank`.

### Admin controller (all types)

- **REQ-EXT-020** — `index()` flow, in this order: `load->language(route)`; `document->setTitle`;
  `load->model('setting/setting')`; if POST and `validate()`: `editSetting('<type>_<code>', post,
  store_id)`, set `session->data['success']`, redirect to
  `marketplace/extension&type=<type>`; else render form with breadcrumbs, `action`, `cancel`
  links (always `user_token`), `error_*` strings, current values from `config->get()` with POST
  precedence, `header`/`column_left`/`footer`. Pattern copied from
  `admin/controller/extension/module/html.php` and `…/payment/cod.php`.
- **REQ-EXT-021** — `validate()` first checks `user->hasPermission('modify', '<route>')`
  (REQ-ARCH-064) then field rules; each error stored in `$this->error['<field>']` and rendered
  next to the field; never `die()`.
- **REQ-EXT-022** — Module extensions that support multiple instances take `module_id` from GET and
  use `model_setting_module->addModule|editModule`; single-instance ones use `editSetting`.
- **REQ-EXT-023** — Optional `install()` / `uninstall()` methods in the admin controller are
  **empty or absent**; all DB work is in migrations (REQ-DB-040), because admin "Install" must
  not be the only way to reach a working state.
- **REQ-EXT-024** — Admin templates extend the stock admin look: `{{ header }}{{ column_left }}`
  … `{{ footer }}`, Bootstrap 3 classes already in core, no external CDN, no inline JS other
  than passing data; form field names equal the setting key.

### Settings

- **REQ-EXT-030** — Setting keys follow reference §3: `<type>_<code>_<field>`; minimum keys:
  `status` (0/1) for every type, `sort_order` for payment/shipping/total, `geo_zone_id` for
  payment/shipping (0 = everywhere), `order_status_id` for payment, `tax_class_id` for
  shipping/total fees.
- **REQ-EXT-031** — Defaults are inserted by the install migration with `status = 0`; an extension
  is never enabled by migration except in environments where the owning spec says so.
- **REQ-EXT-032** — Reading a setting in catalog code always uses `$this->config->get(key)` with an
  explicit fallback for missing keys (`?: default`); no direct `oc_setting` SQL (REQ-ARCH-142).

### Catalog contracts

- **REQ-EXT-040** — **Module**: `ControllerExtension<Type><Code>::index(array $setting)` returns a
  string; returns `null` when it has nothing to show (core omits it). Reads language via
  `load->language()`. Must not call `document->addScript/addStyle` without checking it has not
  been added (core de-duplicates by path; still prefer theme assets).
- **REQ-EXT-041** — **Payment**: model `getMethod($address, $total)` returns `[]` when unavailable
  (status off, geo zone mismatch, `payment_<code>_total` above cart total, cart needs no shipping
  when relevant) else `['code'=><code>,'title'=>…,'terms'=>…,'sort_order'=>…]`. Controller `index()`
  returns the confirm-button HTML; `confirm()` verifies
  `session->data['payment_method']['code']==<code>` before calling
  `model_checkout_order->addOrderHistory($session->data['order_id'], $status_id)` and replies
  JSON `{ "redirect": … }` with header `Content-Type: application/json`.
- **REQ-EXT-042** — **Shipping**: model `getQuote($address)` returns `[]` or
  `['code','title','quote'=>[<key>=>['code'=>"<code>.<key>",'title','cost','tax_class_id','text']],
  'sort_order','error'=>false|string]`; `text` is produced with
  `currency->format(tax->calculate(cost, tax_class_id, config_tax), session currency)`.
- **REQ-EXT-043** — **Total**: model `getTotal(&$total)` appends one or more
  `['code','title','value','sort_order']` to `$total['totals']`, accumulates tax per rate id into
  `$total['taxes']`, adds to `$total['total']`; it must be a pure function of cart/session state
  (called several times per request).
- **REQ-EXT-044** — Extensions never write to `session->data` keys owned by core
  (`cart`, `order_id`, `payment_method`, `shipping_method`, `customer_id`, …) except the
  documented ones for their type; own state uses `session->data['ocs_<code>_…']`.
- **REQ-EXT-045** — Money is handled as float in core models; extensions round with
  `round($v, 4)` only at the points core does, and always display through `currency->format`.

### Install / uninstall / permissions

- **REQ-EXT-050** — Each extension owns its migrations in `custom/extensions/<code>/db/`
  numbered locally (`001_install.sql|php`, `002_…`) and the build/migrate step namespaces them
  as `ext:<code>:<NNN>`. The install migration performs, in order: create tables ->
  insert `oc_extension` row -> insert default settings (status 0) -> grant permissions
  (`access`,`modify` for the extension route to user group 1) -> register events. The uninstall
  migration (`NNN_uninstall.php`, run only by an explicit `migrate:down <code>`) reverses it.
- **REQ-EXT-051** — Verify cross-checks `extension.json` declarations against what migrations
  create (tables/settings/events/routes) and against files present; mismatch fails.
- **REQ-EXT-052** — Uninstall never drops data tables unless `--purge` is passed explicitly.

### Libraries and events inside extensions

- **REQ-EXT-060** — Domain logic: `system/library/ocs/<name>.php` (`namespace Ocs; class <Name>`),
  sub-namespaces map to subdirectories (`Ocs\Pricing\Rule` -> `system/library/ocs/pricing/rule.php`).
  The class is instantiated with `new \Ocs\Name($this->registry)` or loaded with
  `$this->load->library('ocs/name')` (available as `$this->name`). Prefer explicit `new` when
  the same name could collide with a core registry key (e.g. do not name a library `cart`).
- **REQ-EXT-061** — Event handlers are controller methods in
  `catalog|admin/controller/extension/<code>/events.php` (route `extension/<code>/events/<method>`),
  signature `(&$route, &$args[, &$output])`. One handler method per trigger; names describe
  the trigger (`afterAddOrderHistory`).
- **REQ-EXT-062** — Handlers are defensive: they check the arguments' shape, never assume
  `$output` is set on `before`, and never throw to the customer (log with `[ocs_<code>]` and return).

### Scaffolding and vendoring

- **REQ-EXT-070** — `node scripts/new-extension.mjs <code> <type> --spec SPEC-0NN` creates the full
  skeleton of REQ-EXT-011 with language stubs for all enabled languages, an
  `extension.json`, install migration, a unit-test stub, and registers nothing outside the
  extension directory. It refuses to overwrite.
- **REQ-EXT-071** — Third-party extension: copied unchanged into `custom/extensions/<vendor_code>/`
  with `VENDOR.md` (name, source URL, version, licence, date, SHA-256 of the archive). It may
  keep its own code prefix; verify exempts it from `ocs_` naming and from the lint rules of
  SPEC-009, but not from collision rules or from needing an `extension.json`. Changes to it
  are made only through OCMOD (SPEC-007).

## Non-functional requirements

- **REQ-EXT-080** — Extension enable/disable is a single setting (`status`); with status 0 the
  extension adds no output, no events fire a handler body (handlers early-return on
  `config->get('<type>_<code>_status')` falsy), and no queries run on storefront pages.
- **REQ-EXT-081** — An extension adds at most one DB query per storefront page when enabled and
  idle (cache others, REQ-ARCH-110).
- **REQ-EXT-082** — No extension depends on another extension's tables; cross-extension
  communication happens through library classes declared in `requires`.

## Data requirements

Per extension tables `oc_ocs_<short>_<entity>` (SPEC-001 REQ-ARCH-024/055), created by its
install migration only.

## Edge cases

- Same setting key declared by two extensions -> verify error.
- Extension enabled but table missing (migration not run) -> `status` read yields 0; handlers
  must not fatally fail (REQ-EXT-062).
- Multi-store: settings saved with the current `store_id`; defaults inserted for store 0 only.
- Language missing in admin language dir -> core shows raw keys; verify prevents (REQ-ARCH-025).

## Acceptance criteria

- [ ] Scaffolder output passes verify and builds without edits for every type.
- [ ] A sample `ocs_hello` module installs from migrations only, appears in
      `marketplace/extension?type=module`, can be placed in a layout, renders, and uninstalls.
- [ ] A sample payment and shipping extension appear in checkout and complete an order with
      status from settings (manual check recorded in the owning spec).
- [ ] Declaration cross-check (REQ-EXT-051) fails when a declared table is missing.
