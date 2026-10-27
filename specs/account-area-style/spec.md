# SPEC-063 — Customer Account Area in the Site Style

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-006 (theme), SPEC-015 (customer accounts), SPEC-046 (forms)

## Problem

`account/account` and the other pages of the account area (orders, addresses, wishlist, downloads,
rewards, returns, transactions, recurring) still look like stock OpenCart: bare `h2` headings with
blue link lists, Bootstrap-3 bordered tables, a plain `list-group` sidebar with no active item.
The rest of the storefront (header, cards, forms, cart) has the site style (tokens, radii, quiet
borders, SVG icons).

## Functional requirements

### Dashboard (`account/account.twig`)

- **REQ-ACC-001** — The page shows a heading ("My account"; core gives no first name, so none is shown) and a grid of section cards:
  Account, Orders, Partner (affiliate), Subscription. Every entry is a tile link with an SVG icon (existing sprite, `#i-*`),
  a title and, where the language file has one, nothing more (no invented data).
- **REQ-ACC-002** — Grid: 1 column on phones, 2 from 40 rem of the page container (container query as in SPEC-061/062), cards use
  `--r-lg`, a 1 px quiet border, hover lift consistent with product cards; tiles are at least 44 px tall.
- **REQ-ACC-003** — Conditional entries keep their conditions (reward, credit cards, affiliate add/edit/tracking); all template
  variables and link targets stay as in core.

### Sidebar (`extension/module/account.twig`)

- **REQ-ACC-004** — The sidebar is a card with an SVG icon per item and an **active** state for the current page
  (`theme.js` compares the item route with `?route=` and sets `aria-current`; the module controller exposes no current route). Logout is visually separated
  at the bottom. The module keeps the core variables.
- **REQ-ACC-005** — On phones the sidebar collapses to a horizontally scrollable pill strip above the content (or a compact
  card); it is never taller than one screen.

### Shared account pages

- **REQ-ACC-006** — `h1`/`h2` in `#account-*` containers use the same heading scale as other content pages; the content
  area sits in a card-like surface with consistent spacing.
- **REQ-ACC-007** — Order list, download, reward, transaction, return and recurring lists render as a modern table (SPEC-050
  table tokens) on wide containers and as stacked cards (label/value pairs via `data-label`, set by `theme.js` from the header cells) on phones; status gets a neutral
  pill badge; the "view" button becomes an icon/secondary button.
- **REQ-ACC-008** — Address list: one card per address with Edit / Delete actions; "New address" as the primary button.
- **REQ-ACC-009** — Wishlist page keeps working with the cookie bookmarks of SPEC-044 and uses the site product-card/table style.
- **REQ-ACC-010** — Empty states ("no orders yet" etc.) show an icon, a short text and a primary "Continue" button.
- **REQ-ACC-011** — Edit/password/address/newsletter/affiliate forms use the SPEC-046 form system and a narrow readable form width
  (max 40 rem), buttons row aligned with the form.
- **REQ-ACC-012** — Login/register/forgotten pages are in scope only for spacing and the card surface; no logic changes.

### Constraints

- **REQ-ACC-013** — Only `custom/theme/ocs` changes (templates + `stylesheet.css` + at most small language additions via the
  project's language mechanism); `core/` untouched; tokens only, no hard-coded colours; icons only from the SVG sprite; no
  controller/model changes (variables available in core's controllers are enough).
- **REQ-ACC-014** — Light/dark tokens, RTL-safe logical properties, `prefers-reduced-motion` respected, focus rings visible.

## Acceptance criteria

- [ ] `/index.php?route=account/account` shows icon section cards in the site style on 375 px and 1280 px.
- [ ] Sidebar highlights the current account page on every account route.
- [ ] Order list is readable on 375 px without horizontal scroll.
- [ ] Other account pages visibly share the same surface, headings and buttons.
- [x] `npm run verify` green; theme archive rebuilt.

## Open questions

1. Sidebar position: keep wherever the layout puts it (right in the screenshot), or force the account layout to put it on the left? (Proposed: keep; it is an admin layout setting.)
2. Show extra data on the dashboard (last order, bonus balance, wishlist count)? That needs controller data → an event/OCMOD. Proposed: out of scope for now.
