# FAQ for agents

**Where does my change go?** Use the decision tree in [agent-playbook.md](agent-playbook.md): settings → theme → extension → event → OCMOD; never `core/`.

**My template change does nothing.** Check in order: (1) the route's template name and theme directory (`event/theme` falls back to `default`); (2) Twig cache in `DIR_CACHE/template/`
(clear it); (3) a stored override in the admin Theme Editor (`oc_theme`) wins over files; (4) an OCMOD-patched copy in `DIR_MODIFICATION` is preferred over the original file —
refresh modifications; (5) `config_theme` and `theme_<name>_status`.

**My PHP change in an extension is ignored.** `modification()` serves patched copies from `DIR_MODIFICATION`; refresh modifications / clear that directory. OPcache with
`validate_timestamps=0` also needs a reset.

**My event never fires.** Trigger grammar (`catalog|admin/kind/route[/method]/before|after`), `status=1`, handler route resolves to a file and public method, no typo in
the model route (events fire through the `Proxy` only for methods loaded with `$this->load->model`), priority conflicts, and that the row is in `oc_event` (MyISAM, no constraints).

**Why can't I call `parent::` or private helpers of another model?** Models are wrapped in a `Proxy`; share logic via a library in `system/library/ocs/`.

**Output is not escaped.** Twig `autoescape` is off in this core; escape in the controller or with `|escape`.

**A setting reverts after saving in admin.** `editSetting($code,…)` deletes all rows of that `code` for the store before inserting; migrations must use the per-key helper.

**Duplicate settings rows.** `oc_setting` has no unique key; the helper keeps the lowest id.

**Language strings show as keys.** The language file is missing for that code, or `uk-ua` fallback lacks it; check parity ([SPEC-008](../specs/localisation/spec.md)).

**Admin says "A theme has not been assigned".** `theme_<theme>_status` is falsy for `config_theme`; fix status before switching theme.

**Product/category not visible after creation.** `status` defaults to 0 and `noindex` defaults to 1 in ocStore; also check store/layout links (`*_to_store`).

**Why not edit a core file "just once"?** The next core upgrade or a rebuild silently loses it; use the mechanisms in the playbook ([SPEC-001 §4](../specs/project-architecture/spec.md)).

**Can I change the admin directory name?** Yes via the generated `admin/config.php` constants; core code does not hard-code the literal ([reference §1](ocstore-reference.md)).

**Where are the order statuses and what counts as "processing"?** [reference §11](ocstore-reference.md) and [SPEC-011](../specs/orders-checkout/spec.md).
