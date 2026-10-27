# SPEC-059 — Mobile Header Reduction and Tab Bar Order

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-031 (header/footer/mobile shell), SPEC-042 (bottom bar), SPEC-050 (top menu, cart badge)

## Problem

On phones the header stacks the utility strip (contacts, info links, blog/sale/pages, language, currency), the logo, the cart
button, the search and the catalogue button. It takes too much screen and duplicates the bottom tab bar. The tab bar order is
catalogue, compare, cart, lists, profile, and its cart tab has no item-count badge.

## Purpose

A minimal mobile header (logo, search, catalogue/menu button); every other header control lives in the mobile menu; the tab bar has
a fixed order and a cart badge.

## Scope

Viewport width <= 767px (the existing tab bar breakpoint). Theme `ocs`: `header.twig`, `footer.twig` (tab bar), stylesheet, `theme.js`.

## Out of scope

Desktop and tablet header; badges on compare and wishlist tabs; checkout pages (the tab bar is already hidden there); admin settings.

## Functional requirements

- **REQ-MHT-001** — On mobile the header shows only the logo, the search field and the catalogue (menu) button. The utility strip
  (`#top.ocs-util`) and the header action icons/cart (`.ocs-head-actions`) are not visible in the header.
- **REQ-MHT-002** — The mobile menu panel (the full-screen catalogue panel) contains, below the category list: info/top links
  (blog, sale, "pages" list, custom strip links), contacts (phones, messengers, hours), the language switch and the currency switch.
  They are the same working controls as on desktop, not copies: a single element each, no duplicated ids or forms.
- **REQ-MHT-003** — On resize across the breakpoint those controls return to the desktop header and the mobile panel stays valid.
- **REQ-MHT-004** — Tab bar order: 1 menu, 2 compare, 3 wishlist, 4 account, 5 cart. Menu opens the same panel as the catalogue button.
- **REQ-MHT-005** — The cart tab shows a badge with the number of items in the cart (hidden at 0, `99+` over 99), updated on
  ajax cart changes like the desktop cart badge.

## Non-functional requirements

- **REQ-MHT-006** — Without JS the info links, language and currency remain reachable (desktop header unchanged; on mobile they stay inside
  the menu panel markup order); only theme code, no core edits.
- **REQ-MHT-007** — Strings reuse existing language keys; no new hard-coded text.

## Edge cases

- Tab bar disabled in settings (`mobile_bar` off) -> the cart and account are then unreachable from the mobile header; accepted, the setting is a
  conscious choice (see Open decision 2).
- Single language or currency -> their blocks are absent and not moved.

## Open decisions

1. The "menu" in the screenshot is the blue "Каталог" bar; it is kept as the header's menu button and the tab bar menu opens the same panel. Confirm.
2. If `mobile_bar` is off, should the header cart/account be shown again on mobile? Proposed: no.
3. Badges on compare and wishlist tabs: proposed out of scope (only the cart was requested).

## Acceptance criteria

- [ ] At 375px the header has logo, search, catalogue button only.
- [ ] The mobile menu shows info links, contacts, language and currency; switching language/currency works.
- [ ] Tab bar order is menu, compare, wishlist, account, cart; the cart badge matches the cart count and updates after add-to-cart.
- [ ] Desktop header is unchanged; `npm run verify` green; theme archive rebuilt.
