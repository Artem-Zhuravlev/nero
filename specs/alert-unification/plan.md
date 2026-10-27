# PLAN — SPEC-069 Unified Alerts and No Click Outline

## Approach

Visuals live in `stylesheet.css` (one `.alert` block replacing the two existing ones at ~174 and ~1221). Behaviour lives in a small `OCS.alerts`
module in `theme.js`: it strips icons, rebuilds the close button from a template, and runs on load plus a MutationObserver, so alerts created by
core `common.js` (which we cannot edit) are fixed the moment they appear. Templates are cleaned mechanically (icon and `&times;` removed, common
close markup added) so markup is correct without JS. Fixed positioning is CSS only (`.alert-dismissible`); success auto-hide is in the module.
Outline: replace the global focus handling with `:focus:not(:focus-visible) { outline: 0 }` and keep the ring for keyboard focus.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Fix core-generated alerts | MutationObserver in theme.js | Editing `core/` is forbidden; overriding every core handler is brittle |
| Which alerts are fixed | `.alert-dismissible` (flash) | Static info notes must stay in the page flow |
| Close icon | existing sprite `#i-x`, stroke 1.5 | Matches the other icons; no new font |
| Outline | hide only non-keyboard focus | Removing it for keyboard users breaks accessibility |

## Affected files / directories

- `custom/theme/ocs/stylesheet/stylesheet.css`, `custom/theme/ocs/javascript/theme.js`
- ~35 templates in `custom/theme/ocs/template/{account,checkout,affiliate,blog,product,extension/total}` (alert markup)
- `custom/theme/ocs/language/*/` (`text_close` if missing)
- `dist/ocs-theme-<VERSION>.ocmod.zip` via `npm run pack:theme`, `specs/README.md`

## Risks

- Fixed alerts hide content under them -> top offset below the header, auto-hide for success, close button.
- Checkout JS removes alerts by `.alert-dismissible` -> class kept, selectors unchanged.
- Observer loops when normalising -> the normaliser marks processed alerts with `data-ocs-alert`.
