# PLAN — SPEC-070 "Already in cart" Button State

## Approach

`markCart()` in `theme.js` already swaps the button content for the check and `cfg.t.in_cart`. Change it to use `cfg.t.in_cart_btn` (fallback
`in_cart`) for text buttons, and add `.is-just-added` when the state is set by a click (not on load). CSS: stroke-dash animation for
`.is-in-cart .ocs-i path`, pop keyframes, reduced-motion guard. New string key goes through `events.php` into the existing JSON.

## Affected files

- `custom/theme/ocs/javascript/theme.js`, `custom/theme/ocs/stylesheet/stylesheet.css`
- `custom/extensions/ocs_bookmarks/upload/catalog/{controller/extension/ocs_bookmarks/events.php,language/*/extension/ocs_bookmarks/bookmarks.php}`
- `dist/ocs-theme-<VERSION>.ocmod.zip` via `npm run pack:theme`, `specs/README.md`

## Risks

- `#button-cart` text is restored by jQuery `button('reset')` -> existing `resetText` data is kept in sync.
