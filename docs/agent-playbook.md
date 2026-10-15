# Agent playbook — how to do things in this repo

Prerequisites: [ocstore-internals.md](ocstore-internals.md) (how the platform works) and
[SPEC-001](../specs/project-architecture/spec.md) (binding rules). Spec first, then implement.

## Decision tree: where does my change go?

```
Need a different value in admin settings?            -> migration SQL on oc_setting
Need different HTML/CSS/JS on the storefront?        -> custom/theme/ocs (override only changed twig)
Need a new feature/page/widget/payment/shipping?     -> custom/extensions/ocs_<name>
Need to react to / alter something core does?        -> event (oc_event row + handler in extension)
Event can't reach it (need to change core code)?     -> OCMOD file in custom/ocmod
Editing anything under core/?                        -> NO. Stop. Re-read the above.
```

Always locate the core file you are interacting with first (grep in `core/upload/`), read it,
and copy its conventions (tab indentation, `$this->load->…` style, language key names).

## Recipe 1 — Storefront module (widget)

Files under `custom/extensions/ocs_hello/upload/` (code `ocs_hello`, type `module`):

- `admin/controller/extension/module/ocs_hello.php` — class `ControllerExtensionModuleOcsHello`
  with `index()` (settings form, validate, `model_setting_setting->editSetting('module_ocs_hello',
  $this->request->post)`), `validate()` (checks `hasPermission('modify','extension/module/ocs_hello')`),
  optional `install()`/`uninstall()`.
- `admin/language/<lang>/extension/module/ocs_hello.php` — `$_['heading_title']`, `text_*`, `entry_*`, `error_permission`.
- `admin/view/template/extension/module/ocs_hello.twig` — copy structure from
  `core/upload/admin/view/template/extension/module/html.twig`.
- `catalog/controller/extension/module/ocs_hello.php` — `ControllerExtensionModuleOcsHello::index($setting)`
  returns `$this->load->view('extension/module/ocs_hello', $data)`.
- `catalog/language/<lang>/extension/module/ocs_hello.php`,
  `catalog/view/theme/default/template/extension/module/ocs_hello.twig`.
- Logic in `system/library/ocs/hello.php` (`namespace Ocs; class Hello { public function __construct($registry) … }`),
  used via `$this->load->library('ocs/hello'); $this->hello->…` — autoload also resolves
  `new \Ocs\Hello($registry)` directly (lower-cased path).
- Migration `custom/db/migrations/NNNN_ocs_hello.sql`: insert `oc_extension('module','ocs_hello')`,
  grant `access`/`modify` for `extension/module/ocs_hello` to user group 1, set
  `module_ocs_hello_status`.
- Place it in a layout via migration on `oc_layout_module` (or by hand in admin, then capture).

## Recipe 2 — Reacting to core with an event

1. Handler in an extension controller, e.g.
   `catalog/controller/extension/ocs_x/events.php`:
   ```php
   class ControllerExtensionOcsXEvents extends Controller {
     public function afterAddOrder(&$route, &$args, &$output) { /* output = new order id */ }
   }
   ```
2. Migration: `INSERT INTO {{PREFIX}}event SET code='ocs_x_after_add_order',
   `trigger`='catalog/model/checkout/order/addOrder/after', action='extension/ocs_x/events/afterAddOrder',
   status=1, sort_order=0` (guard with `WHERE NOT EXISTS`).
3. Trigger formats: `catalog|admin/` + `controller|model|view|language` + `/<route>/<method or empty>` + `/before|after`.
   `view` events receive `(&$route, &$data, &$output)` and can add variables to `$data` — the
   preferred way to inject data into a core template without OCMOD.
4. Returning a value from a `before` handler replaces the original call.

## Recipe 3 — Theme override

Copy the needed template from `core/upload/catalog/view/theme/default/template/<route>.twig` to
`custom/theme/ocs/template/<route>.twig` (same relative path) and edit the copy. Put CSS in
`custom/theme/ocs/stylesheet/stylesheet.css` (or `.scss`), load it by overriding
`common/header.twig`. The theme needs an admin theme extension (`extension/theme/ocs`, copy of
`admin/controller/extension/theme/default.php`) and settings `theme_ocs_status=1`,
`config_theme=ocs` via migration.

## Recipe 4 — OCMOD patch

`custom/ocmod/ocs_<concern>.ocmod.xml` (see section 9 of internals). Prefer `position="after"`
on a unique, short `<search>` line; test with a refresh; keep each file to one concern;
document the core file/version it targets in a comment. Register in `oc_modification`
(`name, code, author, version, link, xml, status=1, date_added`) through a migration, then refresh.

## Recipe 5 — Settings / data change

Write `custom/db/migrations/NNNN_<slug>.sql`, idempotent:

```sql
INSERT INTO {{PREFIX}}setting (store_id, `code`, `key`, `value`, serialized)
VALUES (0, 'config', 'config_meta_title', 'My Store', 0)
ON DUPLICATE KEY UPDATE ... ;  -- or: DELETE old row, INSERT new (no unique key on setting!)
```

Note `oc_setting` has no unique key on (store_id, code, key): use delete-then-insert or
`UPDATE … ; INSERT … WHERE NOT EXISTS`. Never edit an applied migration; add a new one.

## Recipe 6 — New page/route (storefront)

Controller `catalog/controller/extension/ocs_x/page.php` (route `extension/ocs_x/page`), model
if DB is needed, Twig template; give it an SEO URL with a migration row in `oc_seo_url
(store_id, language_id, query='extension/ocs_x/page', keyword='my-page')`; optionally a layout
row in `oc_layout_route`.

## Recipe 7 — Adding a language string

Add the key to the extension's/theme's language file for **every** enabled language (`uk-ua`,
`ru-ru`, `en-gb`). Never edit core language files; to change a core string use an event on
`language/<route>/after` that overrides `$output[...]`, or `oc_translation`.

## Checklist before saying "done"

- [ ] Spec exists and is approved; requirement IDs referenced.
- [ ] No file in `core/` changed (`git status`, manifest check).
- [ ] No SQL in controllers/templates; IDs cast, strings escaped; output escaped in Twig.
- [ ] All enabled languages updated.
- [ ] Migration idempotent and numbered; extension install/uninstall paths covered.
- [ ] OCMOD refresh + cache clear performed after patch/event changes.
- [ ] `npm run verify` green (once SPEC-001 tooling exists); commit ends with `[SPEC-NNN]`.
