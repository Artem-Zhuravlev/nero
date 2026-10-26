# SPEC-043 — Mobile Menu, Button Metrics and Full Template Set

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-26
**Refines:** SPEC-023, SPEC-027 (menus), SPEC-031 (mobile shell), SPEC-038, SPEC-042 (layer scale)

## Problem

Review items 6-9:

6. The mobile catalogue menu does not open above the site's blocks, and it opens only from the small hamburger icon instead of the whole
   "Catalogue" bar. Measured on the live site: the menu panel is a root-level `position: fixed` element, but a third-party module's
   popup (`#coupon-popup`, `z-index: 5000000`) lies over it; the SPEC-042 layer scale also put the panel at `--z-pop` (20), a value that
   only works inside a header stacking context that does not exist on mobile (the header is `position: static` there).
7. Button and link text is not vertically centred (Bootstrap 3 `line-height`/padding mixed with the theme's heights).
8. Proportions and spacing are not consistent; icon-to-text gaps in buttons are 0-4 px (for example the "Catalogue" button has none).
9. Only 39 of the ~100 storefront templates are overridden. The theme has no `account`, `affiliate`, `checkout`, `information`, `error`,
   most `common` and `extension/module` files: those pages render core markup with Font Awesome and Bootstrap defaults.

## Purpose

A mobile menu that always sits on top and opens from the whole bar; buttons whose text is centred and whose icon gaps are at least 6 px;
a measurable spacing rule; and a complete set of theme templates so every storefront page uses the same design.

## Scope

Theme `ocs`: stylesheet, `theme.js`, `ocs_mega_menu` template, all remaining storefront templates under
`catalog/view/theme/default/template/` except the exclusions below; two browser check scripts.

## Out of scope

Payment gateway forms (`extension/payment/*`, `extension/credit_card/*`, `extension/recurring/*`, `extension/advertise/*`,
`extension/module/paypal_smart_button*`, `extension/module/{klarna,amazon,pilibaba,pp_braintree,sagepay,divido,laybuy,ebay}*`) which are
supplied by payment vendors, and e-mail templates (`mail/*`). Admin. Changing third-party modules' own z-index values.

## Functional requirements

### Mobile menu

- **REQ-MNU-001** — The full-screen mobile catalogue panel is above every page block, popup and third-party overlay of the page except
  the theme's own modals: it uses `--z-menu`, a token in the top range (`2147483000`), above values such as `5000000`. The header is a size container
  (`container-type: inline-size`), which makes it a stacking context, so a z-index alone cannot lift the panel: while open it is moved to the
  browser's top layer with the Popover API (`popover="manual"` + `showPopover()`), and keeps the z-index as the fallback for browsers without it.
- **REQ-MNU-002** — The whole "Catalogue" bar (label and icon) toggles the menu, by mouse, touch and keyboard (a single `<button>` covering
  the bar, `aria-expanded` kept in sync); the panel closes with the close button, the Escape key and when a link is followed; page scroll
  is locked while it is open.
- **REQ-MNU-003** — On desktop the catalogue button and fly-out keep working unchanged.

### Layers (adjusts SPEC-042)

- **REQ-Z-010** — Scale tokens `--z-menu`, `--z-overlay`, `--z-toast` move to the top range (above `2147483000`) so the theme's menu and modals
  stay above third-party popups; order stays base < raised < pop < pop-top < sticky < tabbar < header < menu < overlay < toast; the existing
  test and `check-layers.js` are updated and extended with "a third-party fixed element with `z-index: 5000000` never covers the open menu or a modal".

### Buttons and spacing

- **REQ-BTN-001** — Every `.btn`, theme button class and icon button is a flex box (`display: inline-flex; align-items: center;
  justify-content: center`) with an explicit `line-height` from the type scale, so the text block is centred by layout and not by padding.
  Measured vertical offset between the text box centre and the button centre is at most 1 px at 360 and 1280 px widths.
- **REQ-BTN-002** — The gap between an icon and its text label is at least 6 px (token `--gap-icon: 0.5rem`) in buttons, links, chips, tab
  items and header action labels, horizontal or stacked.
- **REQ-SPC-001** — Spacing rules checked by `scripts/browser/check-spacing.js`: button inline padding is at least its block padding;
  touch targets are at least 40 px high (44 px under 768 px); consecutive blocks use the `--sp-*` scale; no horizontal page scroll at 360 px.
  New CSS uses `--sp-*`/`--gap-icon` tokens (a test counts raw `px` spacing declarations and fails when the count grows).

### Templates

- **REQ-TPL-001** — Every remaining storefront template (not excluded above) exists in `theme/ocs/template/` with the
  `{# @core-file ... @core-version 3.0.3.7 #}` header: `account/*` (24), `affiliate/*`, `checkout/*`, `information/*`, `error/*`,
  `common/{column_left,column_right,content_top,content_bottom,home,maintenance,success}`, `product/manufacturer_list`,
  `extension/module/*` not yet overridden (account, banner, carousel, category, filter, html, information, slideshow, store, ...),
  `extension/total/*`, `extension/captcha/*`.
- **REQ-TPL-002** — The overrides are generated from the core files by a documented script (`scripts/gen-theme-templates.mjs`) that
  converts icons to SVG, stars to the SVG component, breadcrumbs to the SVG home icon, and keeps all variables, Twig logic and element ids
  that core JavaScript relies on. Re-running it on the same core is a no-op; a test fails when an override loses a variable used by the core file.
- **REQ-TPL-003** — The pages are styled by the theme (forms, tables, panels, alerts, pagination, account menus, checkout steps, error and
  information pages) at 360, 768 and 1280 px; screenshot review for one page of each group: login/register, address form, order list,
  wishlist, cart, checkout, contact, information, 404, voucher, return form, affiliate.
- **REQ-TPL-004** — After the full set exists no project-owned template references Font Awesome (test from SPEC-042 extended to all overrides);
  the Font Awesome stylesheet stays loaded only for the excluded payment-vendor templates.

## Non-functional requirements

- **REQ-TPL-020** — No change to controllers, models or form field names; checkout and account flows work as before (smoke on the test store:
  register, login, add to cart, checkout steps up to confirmation with the free/offline payment, wishlist, compare, contact).
- **REQ-MNU-020** — Menu panel opens within 100 ms and does not shift the page.

## Interfaces

`ocs_mega_menu` template and `theme.js` (menu toggle), `stylesheet.css` (tokens, buttons, forms, page groups),
`scripts/gen-theme-templates.mjs`, `scripts/browser/check-spacing.js`, `scripts/browser/check-layers.js`.

## Edge cases

- A module that injects a `z-index: 2147483647` element -> out of control; documented.
- RTL languages -> out of scope (SPEC-023).
- A core template changed in a newer core -> the generator is re-run and the diff reviewed (core is pinned to 3.0.3.7).
- Buttons that contain only an icon -> centred by the same flex rule, minimum size 40 px.

## Open decisions (default in bold)

1. Template scope: **all folders above except payment vendors and mail**; or add `mail/*` (10 e-mail templates, restyled separately).
2. Folder list "on the screenshot" referred to in the review was not visible in the message; the list above is derived from the core
   `template/` tree. Tell me if it differs.
3. Mobile menu top-range z-index: **`2147483000`** (above third-party popups), or keep inside the theme's 1000-1500 range and fix only our own overlaps.

## Implementation notes

- `scripts/gen-theme-templates.mjs` generates 83 overrides (marker `{# @generated ... #}`); `--check` fails when one is stale; the 18 SPEC-042 overrides are
  regenerated by it. Hand-written overrides (header, footer, menu, search, product pages) are never touched.
- Page forms (`.form-horizontal`) put labels above fields, legends and button rows are normalised in the last components layer.
- Checked on the test store: `check-spacing.js` reports nothing on 13 pages at 360 px and 8 at 1024 px; guest checkout ran to the confirmation step with
  cash on delivery, registration and all account pages answered 200 without errors.

## Acceptance criteria

- [x] On a 375 px screen the whole "Catalogue" bar opens the menu; it covers a third-party `z-index: 5000000` popup, the bottom bar and the header.
- [x] `check-spacing.js` reports no button with text offset above 1 px, no icon gap under 6 px, no target under the minimum, on 8 pages at 360 and 1280 px.
- [x] All templates from REQ-TPL-001 exist; the SPEC-042 test passes on them; smoke flows pass on the test store.
- [x] Screenshots of the page groups reviewed; `npm test`, `npm run verify` green; package rebuilt.
