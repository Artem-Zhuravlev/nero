# SPEC-031 — Header, Footer, Announcement Bar, Mobile Shell

**Status:** in-progress
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-23
**Refines:** SPEC-024 REQ-MOY-020, 021, 023, 024, 025, 060 · **Depends on:** SPEC-023, SPEC-006

## Problem

Core header/footer lack the utility strip, messengers, announcement bar, counters and mobile tab bar of the reference.

## Purpose

Configurable header, footer and mobile shell as theme parts with an admin settings module.

## Scope

Theme overrides `common/header` (extended), `common/footer`, `common/menu` slot; extension `ocs_shell` with settings (contacts,
messengers, links, footer groups, announcement); mobile bottom bar.

## Out of scope

Menu content (SPEC-027), search (SPEC-028), cities (SPEC-032), chat widget vendor scripts (slot only, P3).

## Functional requirements

- **REQ-SHL-001** — Announcement bar above the header: one message + optional link per language, date range, dismissible
  (remembered in localStorage by message id).
- **REQ-SHL-002** — Utility strip: language switcher (uk/ru/en), phone numbers, call-centre hours, messenger buttons (Telegram,
  Viber, Messenger URLs), secondary links (Blog, Promotions, Stores, Order tracking, Club); every item optional.
- **REQ-SHL-003** — Header icons compare, wishlist, account, cart with counters; counters update through the existing core
  `#wishlist-total`/cart hooks (REQ-THM-013); checkout button inside the cart panel (SPEC-026).
- **REQ-SHL-004** — Header is sticky with blur (SPEC-023 REQ-DSG-020); logo, menu slot, search slot keep their ids.
- **REQ-SHL-010** — Footer groups: Customers (delivery, payment, returns, warranty), Stores network, Top categories; contacts
  (phones, hours, address), messengers, social links, optional store-app badges; groups collapse on mobile.
- **REQ-SHL-011** — Footer content comes from settings and information pages (core `information`), no literal text in templates.
- **REQ-SHL-020** — Mobile bottom tab bar (Home, Catalogue, Cart, Lists, Profile) below 768 px; Catalogue opens the mobile menu
  (SPEC-027); active tab highlighted; hidden on checkout.
- **REQ-SHL-030** — Optional third-party chat widget slot, loaded only after marketing consent (SPEC-017), never render-blocking (P3).

## Non-functional requirements

- **REQ-SHL-040** — Landmarks `header nav main footer` (REQ-THM-041); every control has a text/aria label; contrast >= 4.5:1.
- **REQ-SHL-041** — uk/ru/en strings; PHP 7.4; settings read with one cached query; each block switchable.

## Data requirements

Settings `module_ocs_shell_*` (serialized per language where needed); no tables.

## Edge cases

- No phone configured -> strip item hidden. All items off -> strip not rendered.
- Announcement end date passed -> not rendered.

## Acceptance criteria

- [ ] Header and footer render with all blocks on and off at 360/768/1280 px without horizontal scroll.
- [ ] Mobile tab bar shows below 768 px and not on checkout.
