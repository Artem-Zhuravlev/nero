# SPEC-069 — Unified Alerts and No Click Outline

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-006 (theme), SPEC-067 (style refresh)

## Problem

Alerts look different across the storefront. Messages created by core's `common.js` (wishlist, compare, cart) keep the stock look: a Font Awesome
`fa fa-check-circle` icon, a text `&times;` close button, and a plain block above the page that pushes content down. Messages from theme templates
carry an inline SVG icon, and some have no close button at all (`alert-info`, review messages, `payment_method`). Long text or the cross can overflow
the box. Clicking links, buttons and cards draws a browser outline ring.

## Purpose

One alert design for the whole storefront: text only, a thin stylish close icon on every alert, floating messages that never overflow. No outline
ring after a mouse click.

## Scope

Theme `custom/theme/ocs` (stylesheet, `theme.js`, templates with alerts) and alert markup built in theme-owned JS. Storefront only; admin is untouched.

## Out of scope

Admin alerts, new message types, toast queue/stacking animations beyond a simple fade, changes to `core/`.

## Functional requirements

- **REQ-ALR-001** — Every `.alert` on the storefront (server-rendered, built in JS, or injected by core's `common.js`) contains only its text and one
  close button. No `fa`/`fa-*` icon and no `svg.ocs-i` status icon remains inside an alert.
- **REQ-ALR-002** — The close button is the same everywhere: the sprite icon `#i-x` (thin 1.5px stroke, `currentColor`, 16px) inside a 28px round
  hit area with a subtle hover background. The stock `&times;` is not used.
- **REQ-ALR-003** — Alerts get one shared look driven by existing tokens: radius `--r-md`, padding, `--fs-sm`, success/danger/warning/info colours as
  in the current stylesheet, no border.
- **REQ-ALR-004** — Flash alerts (`.alert-dismissible`, including those created by `common.js`) are `position: fixed`, centred at the top below the
  sticky header, `max-width: min(32rem, 100vw - 2 x gutter)`, above all page layers (`z-index` over header/cart panel). Neither text nor cross may
  leave the box: text wraps (`overflow-wrap: anywhere`), the cross has reserved space (right padding), the box never exceeds the viewport at 320px.
- **REQ-ALR-005** — Fixed alerts do not shift page content. Success alerts fade out after 6 s; error/warning alerts stay until closed. Closing
  removes the element.
- **REQ-ALR-006** — A normaliser in `theme.js` applies REQ-ALR-001/002 to alerts present on load and to every alert added to the DOM later
  (MutationObserver), so core scripts and third-party modules are covered without editing them.
- **REQ-ALR-007** — Templates in `custom/theme/ocs/template` and theme-owned JS no longer emit an icon or `&times;` in alerts and use the common
  close markup, so the result is correct before JS runs.
- **REQ-ALR-008** — Static notices (`alert-info` such as the minimum-quantity note, inline form messages such as `#review-msg`) are not fixed but
  still get the cross and the common look.
- **REQ-ALR-009** — No outline ring after a mouse/touch click on any element (links, buttons, cards, inputs, summary, tabindex). The keyboard focus
  ring (`:focus-visible` from keyboard) stays for accessibility.
- **REQ-ALR-010** — The close button has `aria-label` from the language file (`text_close`), alerts carry `role="alert"`.

## Non-functional requirements

- **REQ-ALR-011** — No new libraries or fonts; the cross reuses `#i-x`. CSS uses existing tokens.
- **REQ-ALR-012** — `npm run verify` and `npm test` stay green; the theme archive is rebuilt (`npm run pack:theme`).

## Interfaces

CSS selectors `.alert`, `.alert-dismissible`, `.alert .close`; JS `OCS.alerts` (normaliser); no new routes or settings.

## Edge cases

- Several alerts at once -> stack vertically in a fixed container, no overlap.
- Alert text contains HTML/links (`HP LP3065 ... <a>wishlist</a>`) -> link keeps its style, nothing clipped.
- Very long word/URL -> wraps, cross stays inside.
- Alert inside a checkout accordion panel built by JS -> normalised, treated as flash (fixed) per REQ-ALR-004.
- JS disabled -> template markup already clean; alert is fixed by CSS, cross is inert.
- Keyboard user -> Tab to the cross shows the focus ring.

## Acceptance criteria

- [ ] Adding to wishlist/compare shows the alert from the screenshot as text only, with the thin cross, fixed, nothing overflowing, at 320px and desktop.
- [ ] `grep` finds no `fa fa-` and no `&times;` in storefront alerts; `i-info`/`i-alert`/`i-check` icons are gone from alert markup.
- [ ] Every `.alert` variant (success, danger, warning, info) has a working cross.
- [ ] Clicking a button/link/card shows no outline; Tab still shows a ring.

## Implementation notes

- REQ-ALR-007/010: templates drop the icon and `&times;`; the close button is added by `OCS.alerts` (no per-template markup). Its `aria-label` comes
  from a uk/ru/en map keyed by `<html lang>` because the theme has no language files of its own.
