# PLAN — SPEC-008 Localisation

## Approach

Treat the language set as data (`languages.json`) mirrored into the DB by a migration; treat
translations as files checked by parity. A `langpack` build layer carries pack files for languages
not in core. Helpers (`Ocs\I18n`) live in a library-type extension `ocs_i18n`.

## Decisions

| Decision | Choice | Why |
| -------- | ------ | --- |
| Language source of truth | `languages.json` -> migration | reviewable in git, reproducible |
| Fallback | rely on core uk-ua fallback, require uk-ua files | matches `Language::$default` |
| Overrides | events/`oc_translation`, never core files | upgrade safety |
| Slug transliteration | helper with KMU 2010 table | consistent Ukrainian URLs |

## Affected files

`custom/i18n/*`, `custom/langpack/**`, `custom/extensions/ocs_i18n/**`,
`scripts/lib/check-i18n.mjs`, migration `0003_languages.php`, build layer for `langpack`
(update SPEC-003 when implemented: add layer `custom/langpack/<code>/{catalog,admin}` ->
docroot, collision rules unchanged).

## Risks

- Third-party Russian packs for ocStore differ in key coverage -> parity check against uk-ua keys.
- Changing the default language later renumbers nothing but affects fallback semantics.
