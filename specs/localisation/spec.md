# SPEC-008 — Localisation (languages, currencies, translations)

**Status:** draft
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-15
**Refines:** SPEC-001 section 9, section 16 · **Facts:** verified in this spec's "Platform facts"

## Platform facts (verified in `core/upload/`)

1. Shipped language directories: **`uk-ua` and `en-gb` only**, in both `catalog/language/` and
   `admin/language/`. The distribution label says "UK-EN-RU" but there is **no `ru-ru`** folder.
2. `oc_language` default rows: id 1 `uk-ua` (sort 1), id 2 `en-gb` (sort 2), both enabled;
   `config_language=uk-ua`, `config_admin_language=uk-ua`, `config_currency=UAH`.
3. At runtime the language folder is chosen by the language **code** (`new Language($code)` in
   `catalog/controller/startup/startup.php`); the `directory` column of `oc_language` is not
   used for this.
4. `Language::load($file)` loads `language/uk-ua/<file>.php` **first as a hard-coded fallback**
   (`private $default = 'uk-ua'`) and then `language/<code>/<file>.php` on top; a key missing in
   both returns the key itself (`get()`).
5. Language detection order: session -> cookie -> `Accept-Language` (locale match, then folder
   name) -> `config_language`.
6. Translatable content lives in `*_description` tables keyed by `language_id`; SEO slugs in
   `oc_seo_url` per `(store_id, language_id)`; DB-level string overrides in `oc_translation`
   (applied by `event/translation` on `language/*/after`).

## Problem

The store's languages and currencies are not decided yet and the distribution's pack is
incomplete (no Russian). Every extension, theme and migration must nevertheless be written so
that adding or removing a language is a data/translation task, never a code change.

## Purpose

Define the language/currency model, how languages are enabled via migrations, where translation
files live, parity rules, fallback behaviour and how to add a new language pack.

## Scope

Language set declaration, adding a language, translation file layout and tooling, SEO per
language, currencies, number/date formatting, content-vs-UI translation boundary.

## Out of scope

Choosing the final language set and writing translations of a concrete business (store-config spec).

## Functional requirements

### Language set

- **REQ-I18N-001** — The authoritative list of enabled languages is `custom/i18n/languages.json`:
  ```json
  { "default": "uk-ua",
    "admin_default": "uk-ua",
    "languages": [
      { "code": "uk-ua", "name": "Українська", "locale": "uk_UA.UTF-8,uk_UA,uk-ua,uk,ukrainian", "sort": 1 },
      { "code": "en-gb", "name": "English", "locale": "en-US,en_US.UTF-8,en_US,en-gb,english", "sort": 2 }
    ] }
  ```
  Initial content equals what core ships; changing it requires a spec.
- **REQ-I18N-002** — A migration (`0003_languages`) makes the database match `languages.json`:
  inserts missing `oc_language` rows (never changes `language_id` of existing ones), updates
  `name/locale/sort_order/status`, sets `config_language`/`config_admin_language`. It never deletes
  a language that has content (it sets `status=0`).
- **REQ-I18N-003** — Adding a language `xx-yy` is a procedure, in one spec: (1) language pack
  files for **catalog and admin** copied/created in `custom/langpack/<code>/{catalog,admin}/…`
  (a layer the build copies like an overlay; paths are new so no collision, REQ-ARCH-012); (2)
  `languages.json` updated; (3) migration creating the row and — through a seed or a documented
  admin step captured in a migration — copies of all `*_description` content for existing
  entities (attributes, categories, information pages, …) using the default language's text as
  a placeholder flagged in `custom/i18n/todo-<code>.md`; (4) SEO slugs added; (5) parity check
  green.
- **REQ-I18N-004** — The default language is never removed or renumbered. `uk-ua` files are the
  runtime fallback for every other language (platform fact 4), therefore every custom
  language file must exist for `uk-ua` even if the store's default becomes another language.

### Translation files

- **REQ-I18N-010** — Custom UI strings live only in language files of the owning extension/theme
  (`catalog|admin/language/<code>/extension/<type>/<code>.php`, theme strings per REQ-THM-014).
  Format: PHP file with `$_['key'] = 'text';` lines, UTF-8 no BOM, LF, keys `snake_case`,
  placeholders in `sprintf` style (`%s`, `%d`) with the same count in every language.
- **REQ-I18N-011** — Key naming: `heading_title`, `text_*`, `entry_*`, `button_*`, `error_*`, `help_*`,
  `tab_*`, `column_*` — same prefixes as core so reviewers and tools recognise them.
- **REQ-I18N-012** — **Parity check** (`scripts/lib/check-i18n.mjs`, part of verify): for every custom
  language file, the set of keys is identical in all enabled languages; placeholder counts match;
  no empty values; no value identical to the key; no untranslated marker (`TODO`, `@@`).
  Temporary exception only via `custom/i18n/todo-<code>.md` entries with an owner and date.
- **REQ-I18N-013** — Overriding a core string: `language/<route>/after` event handler (SPEC-007)
  or an `oc_translation` row created by migration (`store_id, language_id, route, key, value`);
  core language files are never edited.
- **REQ-I18N-014** — No string concatenation to build sentences; whole sentences with placeholders
  so word order can differ per language. Plurals use a helper `Ocs\I18n::plural($n, $forms)` with
  three Slavic forms (`1`, `2–4`, `5+/0`) plus English two-form rule, selected by language code.

### Content and SEO

- **REQ-I18N-020** — Entities with translatable fields follow the `_description` pattern
  (SPEC-005 REQ-DB-050); admin forms show one tab per enabled language in `sort_order`.
- **REQ-I18N-021** — Every storefront-reachable entity has an SEO slug per enabled language;
  slugs are transliterated Latin (Ukrainian -> KMU 2010 transliteration) by a helper
  `Ocs\I18n::slug($text, $lang)`; uniqueness enforced per `(store_id, language_id)`.
- **REQ-I18N-022** — `hreflang` links for all enabled languages are emitted on pages that have
  translations, through a `view/common/header/before` handler using `url->link` with the
  language code parameter; `x-default` = default language.
- **REQ-I18N-023** — Language switcher is the core `common/language` module; theme only styles it.

### Currencies, numbers, dates

- **REQ-I18N-030** — Currencies are rows in `oc_currency` managed by migrations (`code`, `symbol`,
  `decimal_place`, `value`); default `UAH` (core); the base currency (`value = 1`) is unique.
  Rate updates are an operational concern (own spec) — never hard-coded in code.
- **REQ-I18N-031** — Display formatting goes through `currency->format()`; dates through the
  language file's `date_format_short|long` and `time_format` keys (core keys), never `date('d.m.Y')`.
- **REQ-I18N-032** — Server locale/timezone: `config_timezone` is set by migration (default
  `Europe/Kyiv` for this project) and the DB session time zone follows (`framework.php` does this).
- **REQ-I18N-033** — Phone, address and postcode formats are not hard-coded; use country/zone tables
  and custom fields configured by migration.

### Administration language

- **REQ-I18N-040** — Admin UI languages are the same set; `config_admin_language` default `uk-ua`;
  each admin user may switch (core feature) — extension admin files exist for all languages
  (REQ-ARCH-025).

## Non-functional requirements

- **REQ-I18N-050** — Parity check runs in < 5 s; output lists `file:key:languages-missing`.
- **REQ-I18N-051** — All source files are UTF-8; build verifies no BOM and no CRLF in language files.

## Data requirements

`oc_language`, `oc_currency`, `oc_translation` rows via migrations; `*_description` and
`oc_seo_url` per language.

## Edge cases

- Language file exists for `en-gb` only -> UI falls back to Ukrainian text (platform fact 4) —
  parity check prevents.
- Browser asks `ru`: no `ru-ru` -> falls to `config_language`. Adding `ru-ru` later follows
  REQ-I18N-003.
- A placeholder-count mismatch (`%s` vs `%s %s`) causes PHP warnings in one language only -> parity
  check.
- Slug collision across languages is allowed; within one language it is an error.

## Acceptance criteria

- [ ] `languages.json` and DB agree after `migrate up` (test compares rows).
- [ ] Parity check fails on a fixture with a missing key and on a placeholder mismatch.
- [ ] Adding a fake language `xx-xx` in a test fixture builds and passes parity with placeholders.
- [ ] hreflang test: header contains links for all enabled languages on the home page.
