# ocStore 3.0.3.7 — verified reference tables

Facts below were read from `core/upload/` (file references given). They back the contracts in
SPEC-003…009. If core is upgraded, re-verify (SPEC-001 REQ-ARCH-100).

## 1. Generated config files (written by `install/controller/install/step_3.php`)

`config.php` (storefront) defines, in this order:
`HTTP_SERVER`, `HTTPS_SERVER` (site URL with trailing slash), `DIR_APPLICATION` (`<root>/catalog/`),
`DIR_SYSTEM` (`<root>/system/`), `DIR_IMAGE` (`<root>/image/`), `DIR_STORAGE` (`DIR_SYSTEM.'storage/'`),
`DIR_LANGUAGE` (`DIR_APPLICATION.'language/'`), `DIR_TEMPLATE` (**`DIR_APPLICATION.'view/theme/'`**),
`DIR_CONFIG` (`DIR_SYSTEM.'config/'`), `DIR_CACHE`, `DIR_DOWNLOAD`, `DIR_LOGS`, `DIR_MODIFICATION`,
`DIR_SESSION`, `DIR_UPLOAD` (all `DIR_STORAGE.'<name>/'`), `DB_DRIVER`, `DB_HOSTNAME`, `DB_USERNAME`,
`DB_PASSWORD`, `DB_DATABASE`, `DB_PORT`, `DB_PREFIX`.

`admin/config.php` defines: `HTTP_SERVER` (=`<url>admin/`), `HTTP_CATALOG` (=`<url>`), `HTTPS_SERVER`,
`HTTPS_CATALOG`, `DIR_APPLICATION` (`<root>/admin/`), `DIR_SYSTEM`, `DIR_IMAGE`, `DIR_STORAGE`,
`DIR_CATALOG` (`<root>/catalog/`), `DIR_LANGUAGE`, `DIR_TEMPLATE` (**`DIR_APPLICATION.'view/template/'`**),
`DIR_CONFIG`, `DIR_CACHE`, `DIR_DOWNLOAD`, `DIR_LOGS`, `DIR_MODIFICATION`, `DIR_SESSION`,
`DIR_UPLOAD`, the same `DB_*`, plus `OPENCART_SERVER` (`https://www.opencart.com/`),
`OPENCARTFORUM_SERVER` (`https://opencartforum.com/`).

`config-dist.php` and `admin/config-dist.php` are **0-byte** files.
`DIR_ADMIN`-style constants do not exist: the admin directory name is hard-wired only through
`HTTP_SERVER` / `DIR_APPLICATION` in `admin/config.php` (so renaming is possible by editing the
generated config — core code uses `HTTP_CATALOG`/`DIR_CATALOG`, not the literal "admin").
Verified by grep: no PHP in `catalog/` or `system/` hard-codes the literal `admin/` (only the
installer and `modification()`'s *output* subfolder name `DIR_MODIFICATION.'admin/'`, which is
independent of the real directory). Renaming the admin directory is therefore safe if
`admin/config.php` constants point to the new name.
Admin dashboard (`common/dashboard`) warns while `DIR_STORAGE == DIR_SYSTEM.'storage/'`, and
`common/security` ships a built-in tool that moves storage — our build sets `DIR_STORAGE`
outside the docroot directly, which silences the warning.

## 2. Admin user creation (installer, `install/model/install/install.php`)

`oc_user` row id 1, group 1, password hash = `sha1($salt . sha1($salt . sha1($password)))` with a
9-char `salt`; `config_email` and a 1024-char `config_encryption` token written to `oc_setting`;
default API user (`oc_api`, key 256 chars) and `config_api_id`; `config_invoice_prefix`
= `INV-<year>-00`. Table prefix substitution is a plain `oc_` string replace on
`DROP/CREATE/INSERT` statements of `install/opencart.sql`.

## 3. Setting key conventions

| Kind | `oc_setting.code` | key pattern | examples |
|------|-------------------|-------------|----------|
| Store config | `config` | `config_*` | `config_theme`, `config_language`, `config_seo_url`, `config_seo_pro` |
| Module (global) | `module_<name>` | `module_<name>_<field>` | `module_featured_status` |
| Payment | `payment_<name>` | `payment_<name>_<field>` | `payment_cod_status`, `payment_cod_geo_zone_id`, `payment_cod_total`, `payment_cod_order_status_id`, `payment_cod_sort_order` |
| Shipping | `shipping_<name>` | `shipping_<name>_<field>` | `shipping_flat_cost`, `shipping_flat_tax_class_id`, `shipping_flat_geo_zone_id`, `shipping_flat_status`, `shipping_flat_sort_order` |
| Order total | `total_<name>` | `total_<name>_<field>` | `total_handling_status`, `total_handling_fee`, `total_handling_total`, `total_handling_tax_class_id`, `total_handling_sort_order` |
| Theme | `theme_<name>` | `theme_<name>_<field>` | `theme_default_status`, `theme_default_directory`, `theme_default_product_limit`, `theme_default_image_thumb_width` |
| Dashboard/report/feed | `dashboard_<n>` / `report_<n>` / `feed_<n>` | same prefix pattern | `dashboard_order_status`, `report_sale_order_status` |

Rows with `serialized=1` store JSON. There is **no unique key** on `(store_id, code, key)` —
writers must delete-then-insert (what `model_setting_setting->editSetting($code,$data,$store)` does:
deletes all rows of that `code` for the store, inserts the new ones).
Module *instances* (several per extension) live in `oc_module(module_id, name, code, setting JSON)`.

## 4. Extension contracts

| Type | Admin files (route `extension/<type>/<code>`) | Catalog contract |
|------|-----------------------------------------------|------------------|
| module | controller `index()`+`validate()`, language, twig; instances via `model_setting_module->addModule/editModule` | `ControllerExtensionModule<Code>::index(array $setting)` returns HTML string (or nothing); invoked by `common/column_left|right|content_top|content_bottom` via `oc_layout_module` |
| payment | settings form (`payment_<code>_status|geo_zone_id|order_status_id|sort_order|total` …) | model `getMethod($address,$total)` -> `[]` or `['code','title','terms','sort_order']`; controller `index()` returns button HTML; `confirm()` calls `model_checkout_order->addOrderHistory($order_id,$status_id)` and returns JSON `redirect` |
| shipping | settings (`shipping_<code>_*`) | model `getQuote($address)` -> `[]` or `['code','title','quote'=>[ key=>['code'=>'<code>.<key>','title','cost','tax_class_id','text'] ],'sort_order','error']` |
| total | settings (`total_<code>_status|sort_order|…`) | model `getTotal(&$total)` appends to `$total['totals'][]` (`code,title,value,sort_order`), adds to `$total['taxes'][$tax_rate_id]`, updates `$total['total']`; extensions sorted by `total_<code>_sort_order` |
| theme | controller saving `theme_<code>` settings per `store_id`; admin language/twig | storefront theme directory `catalog/view/theme/<code>/` |
| dashboard / report / feed / captcha / currency / fraud / analytics / advertise | `extension/<type>/<code>` | type-specific; copy the closest core example |

Class naming examples: `ControllerExtensionPaymentCod`, `ModelExtensionPaymentCOD` (PHP class names
are case-insensitive — file/route case is what matters), `ControllerExtensionModuleHTML` for
`extension/module/html`.
Payment/total/shipping lists are read with `model_setting_extension->getExtensions($type)` (rows of
`oc_extension`) and filtered by `<type>_<code>_status`. An extension row without a status setting
is invisible.
Extension admin lifecycle: `marketplace/extension?type=<t>` -> install inserts into `oc_extension`
(+ `user_group` permissions `access`/`modify` for `extension/<t>/<code>`), then calls controller
method `install()` if present; uninstall reverses it and calls `uninstall()`.

## 5. Permissions storage

`oc_user_group.permission` is a **JSON object** `{"access":[routes…],"modify":[routes…]}`.
`model_user_user_group->addPermission($user_group_id,$type,$route)` appends to it. Pure SQL
migrations cannot do this safely on older MySQL → permission grants are done in **PHP
migrations** that use that model (SPEC-005).

## 6. Layouts (default ids, `oc_layout`)

1 Home · 2 Product · 3 Category · 4 Default · 5 Manufacturer list · 6 Account · 7 Checkout ·
8 Contact · 9 Sitemap · 10 Affiliate · 11 Information · 12 Compare · 13 Search · 14 Blog ·
15 Blog categories · 16 Blog articles · 17 Manufacturer page. (Names are Ukrainian in this
distribution; use ids/routes, never names.) `oc_layout_route(layout_id, store_id, route)`,
`oc_layout_module(layout_module_id, layout_id, code, position, sort_order)` where `code` is
`<module>` or `<module>.<module_id>`, positions `content_top|content_bottom|column_left|column_right`.

## 7. Default events shipped in `oc_event` (45 rows)

Codes: `activity_*` (customer/address/affiliate/order/return activity log), `mail_*` (customer
add/alert, forgotten, transaction, affiliate, order add/alert, voucher send), `admin_mail_*`
(forgotten, reward, transaction, return), `statistics_*` (review/return/order history),
`advertise_google` (admin menu link, product add/copy/delete, header tag, remarketing views).
Never disable or delete these from a migration. New events use code prefix `ocs_`.

Useful triggers (all valid, from core): `catalog/model/checkout/order/addOrderHistory/before|after`,
`catalog/model/account/customer/addCustomer/after`, `catalog/controller/checkout/success/before`,
`catalog/view/common/header/after`, `catalog/view/<route>/before` (add template vars),
`admin/view/common/column_left/before` (add admin menu items), `admin/model/catalog/product/
addProduct|editProduct|deleteProduct/after`, `language/<route>/after` (override strings).

## 8. Request/engine facts used by tooling

- Autoloader maps `Ocs\Foo\Bar` -> `system/library/ocs/foo/bar.php` (lower-cased), via
  `modification()`; so library classes in namespace `Ocs` need no registration.
- `startup.php` loads `DIR_STORAGE.'vendor/autoload.php'` if present (Twig, ScssPhp live there;
  `system/storage/vendor/` ships in `upload/`). **Moving `DIR_STORAGE` outside the docroot must
  copy the whole `system/storage/` tree including `vendor/`.**
- Catalog `DIR_TEMPLATE` is `view/theme/` (theme dir prefix is part of the Twig route, e.g.
  `default/template/common/header`), admin `DIR_TEMPLATE` is `view/template/`.
- `modification()` searches `DIR_MODIFICATION.'catalog/'|'admin/'|'system/'` + relative path.
- Twig: `autoescape => false`, cache `DIR_CACHE.'template/'`.
- Default session engine `db` (`oc_session`), cookie `OCSESSID`; default language dir `uk-ua`
  (`system/config/default.php`).
- Pre-actions and default events: see `system/config/catalog.php` and `admin.php`.

## 9. Storage engines and DDL facts

`install/opencart.sql`: 157 tables `ENGINE=MyISAM`, 2 `InnoDB`; charset `utf8` / `utf8_general_ci`.
MyISAM = no transactions, no foreign keys; MySQL DDL is non-transactional anyway. Hence migrations
must be idempotent and re-runnable, not atomic.
Key table shapes (for migration helpers):
`oc_setting(setting_id, store_id, code varchar128, key varchar128, value text, serialized)`;
`oc_extension(extension_id, type varchar32, code varchar32)`;
`oc_event(event_id, code varchar64, trigger text, action text, status, sort_order)`;
`oc_seo_url(seo_url_id, store_id, language_id, query varchar255, keyword varchar255)` (non-unique keys);
`oc_layout_route(layout_route_id, layout_id, store_id, route varchar64)`;
`oc_layout_module(layout_module_id, layout_id, code varchar64, position varchar14, sort_order)`;
`oc_module(module_id, name varchar64, code varchar32, setting text)`;
`oc_user_group(user_group_id, name, permission text JSON)`;
`oc_modification(modification_id, extension_install_id, name, code, author, version, link, xml mediumtext, status, date_added)`;
`oc_language(language_id, name, code varchar5, locale, image, directory, sort_order, status)`;
`oc_currency(currency_id, title, code varchar3, symbol_left, symbol_right, decimal_place, value double, status, date_modified)`;
`oc_store(store_id, name, url, ssl)`.
Because `code` is `varchar(32)` in `oc_extension`/`oc_module`, extension codes must be <= 32 chars
(`ocs_` + 28), and event `code` <= 64.

## 10. Languages (verified)

Shipped: `uk-ua`, `en-gb` (catalog and admin). No `ru-ru`. `oc_language` rows: 1 `uk-ua`, 2 `en-gb`.
Language object is built from the language **code** (`new Language($code)` in
`catalog/controller/startup/startup.php`); `Language::$default = 'uk-ua'` is loaded first as a
fallback for every file. Defaults: `config_language=uk-ua`, `config_admin_language=uk-ua`,
`config_currency=UAH`. Catalog `common/header.twig` of the default theme hard-codes jQuery 2.1.1,
Bootstrap 3, Font Awesome, a Google Fonts link and `catalog/view/theme/default/stylesheet/stylesheet.css`.
Theme image settings: `theme_default_image_{thumb,popup,category,manufacturer,product,additional,related,compare,wishlist,cart,location}_{width,height}`,
`theme_default_product_limit` (15), `theme_default_product_description_length` (100),
`theme_default_directory` (`default`), `theme_default_status` (1).

## 11. Orders, catalogue, SEO, admin auth (verified)

- Order statuses (id: en): 1 Pending, 2 Processing, 3 Shipped, 5 Complete, 7 Canceled, 8 Denied, 9 Canceled Reversal, 10 Failed, 11 Refunded, 12 Reversed, 13 Chargeback, 14 Expired, 15 Processed, 16 Voided. Defaults: `config_order_status_id=1`, `config_processing_status=[5,1,2,12,3]`, `config_complete_status=[5,3]`.
- Total extension sort orders: sub_total 1, reward 2, shipping 3, coupon 4, tax 5, credit 7, voucher 8, total 9.
- `checkout/confirm` calls `model_checkout_order->addOrder` (sets `session order_id`); status changes go through `addOrderHistory` (fraud extensions, stock, coupons, mail/statistics events).
- Admin password check: `SHA1(salt.SHA1(salt.SHA1(pw)))` or legacy `md5(pw)`; `user_token` GET param on every admin route; API access via `oc_api` + `oc_api_ip` + `oc_api_session` (`api_token`).
- ocStore extra columns: `oc_product.noindex`, `oc_category.noindex` (default 1), `oc_product_description.meta_h1`. `oc_product.status` defaults to 0.
- Search is LIKE-based (`pd.name LIKE %word%`, tag, optional description); blog uses `MATCH AGAINST`. SeoPro flags: `config_seo_pro`, `config_seopro_addslash`, `config_seopro_lowercase`, `config_valide_param_flag`, `config_valide_params`.
- Cache engines in core: file, apc, mem, memcached, redis; session engines: db, file; mail: mail, smtp. Core has no cron/queue.

## 12. Installer behaviour (verified)

`install/cli_install.php install --db_* --username --password --email --http_server` defines `DIR_OPENCART` as the parent of its own directory, imports `opencart.sql`, creates the admin user, settings and API user, then **writes `config.php` and `admin/config.php` into `DIR_OPENCART`** and checks that `image/` and `system/storage/{download,upload,cache,logs,modification}` are writable there. Never run it against `core/` — run it only on a throw-away copy (parity tests) or replay its DB steps (SPEC-001 REQ-ARCH-032).

## 13. Structured data (SPEC-080, project addition, not core)

| Item | Value |
| --- | --- |
| Settings (group `theme_ocs`) | `theme_ocs_schema_status` (1/0, default 1), `theme_ocs_schema_org_name`, `theme_ocs_schema_org_logo` (empty = store name / logo) |
| Event | `ocs_structured_data`: `catalog/view/*/after` -> `extension/ocs/events/structuredData` (migration 0011); puts one `<script type="application/ld+json">` before the first `</head>` of full pages |
| Library | `Ocs\StructuredData` (`system/library/ocs/structureddata.php`): sanitisers, node builders, `graph()` (safe encoder), `inject()` |
| Facts used | `catalog/view/*/after` receives `($route, &$data, &$output)`; `common/header` output also contains `</head>` and is skipped; module carousels are collected from `extension/module/*` views |
