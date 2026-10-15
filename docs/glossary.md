# Glossary

Short definitions; details are in the linked document.

| Term | Meaning |
|------|---------|
| ocStore | Ukrainian/Russian-community fork of OpenCart 3.0.x; here version 3.0.3.7 build 0002 ([internals §1](ocstore-internals.md)) |
| core | The unmodified distribution in `core/upload/`; read-only |
| custom | Everything we write, under `custom/` (and tooling in `scripts/`) |
| overlay | Files added onto the docroot at build time; may only add new paths |
| build / docroot | `build/www`, generated from core + custom; never edited by hand |
| runtime data | Data created in admin/storefront (products, orders, customers, images); not in git |
| route | `a/b/c` string resolved to `controller/a/b/c.php` or method `c` of `controller/a/b.php` |
| Registry | Container that gives controllers `$this->db`, `$this->config`, `$this->load`, … |
| MVC-L | Model–View–Controller–Language: the core architecture style |
| loader | `$this->load->controller|model|view|language|library|helper|config` |
| event | Hook with trigger `app/kind/route[/method]/before|after`, stored in `oc_event` ([internals §4](ocstore-internals.md)) |
| trigger | The string that selects when an event handler runs |
| OCMOD | XML patch system that writes patched copies into `DIR_MODIFICATION`; core files stay intact ([internals §9](ocstore-internals.md)) |
| vQmod | Older patch system; forbidden here |
| extension | Installable unit of type module/payment/shipping/total/theme/feed/dashboard/report/captcha/… registered in `oc_extension` |
| module instance | One configured copy of a module (`oc_module` row) placed into a layout |
| layout | Mapping route → positions → modules (`oc_layout*`) |
| position | `content_top`, `content_bottom`, `column_left`, `column_right` |
| theme | Directory `catalog/view/theme/<name>/`; falls back to `default` per template |
| total | Order-total extension (subtotal, shipping, tax, coupon, fee …) with `sort_order` |
| geo zone | Set of country/zone pairs used to restrict payment/shipping/tax |
| tax class / rate / rule | Tax configuration tables joined through rules |
| store | A storefront entity (`oc_store`), id 0 = default; settings and links are per store |
| `config_*` | Store settings keys loaded from `oc_setting` into `$this->config` |
| language code | e.g. `uk-ua`; used as folder name at runtime (the `directory` column is unused) |
| `DIR_*` constants | Paths defined in the generated `config.php` ([reference §1](ocstore-reference.md)) |
| `DIR_STORAGE` | Writable data root (cache, logs, session, upload, modification, vendor); should be outside the docroot |
| migration | Numbered idempotent SQL/PHP change applied by `migrate` ([SPEC-005](../specs/database-migrations/spec.md)) |
| seed | Development/demo data applied by `seed`; never in production |
| spec / REQ / TASK / TEST | Spec-driven artefacts and ids ([specs/README.md](../specs/README.md)) |
| SeoPro | Bundled SEO URL library (`system/library/seopro.php`) |
| FPC | Full-page cache for anonymous traffic ([SPEC-019](../specs/performance-availability/spec.md)) |
| DSR | Data-subject request: export/anonymise personal data ([SPEC-014](../specs/operations/spec.md)) |
