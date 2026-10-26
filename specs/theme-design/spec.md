# SPEC-023 — Storefront Visual Design (Apple-style)

**Status:** in-progress
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-23
**Refines:** SPEC-006 (theme `ocs`) · **Source:** apple.com colour language; client brief `Список модулей.docx`

## Problem

SPEC-006 defines the theme skeleton but explicitly leaves visual design to separate specs. The product is a reusable
"reference template" for clients; it needs one coherent, calm, premium look, and a shared UI kit that the
purchased/own modules (OCFilter, Simple, blog, quick order, AJAX cart/search, mega menu, stickers…) can be
styled against.

## Purpose

Define the design tokens (colour, type, spacing, radius, shadow, motion), base components and per-page look of the
`ocs` theme in the spirit of apple.com: light neutral surfaces, a single blue accent, generous whitespace, system
type, soft rounded cards.

## Scope

Design tokens as CSS custom properties in `custom/theme/ocs/stylesheet/`; base components (buttons, inputs,
cards, badges, alerts, breadcrumbs, pagination, tables); unified popup/modal component; styling of header, footer,
menu, category, product and the product card (CSS for all listings; markup override only in `product/category` and `product/product` in this iteration); footer and home are CSS-only; styling hooks (class names/CSS variables) that modules use; styling
of the built-in ocStore blog.

## Out of scope

Module logic (quick order, pre-order, AJAX cart, mega menu, search, stickers, viewed, back-in-stock, category wall,
side category menu) — each gets its own spec after approval of this one. OCFilter and Simple are third-party
(only styled, not built). Checkout is styled last (REQ-THM-051). Copying Apple logos, fonts (SF Pro), images or text
is forbidden — only the colour/spacing language is borrowed. Screenshots in the brief are illustrative only.

## Functional requirements

### Tokens

- **REQ-DSG-001** — All colours are CSS custom properties on `:root`; no hard-coded colour in component rules.
- **REQ-DSG-002** — Light palette: `--bg:#ffffff`, `--bg-alt:#f5f5f7` (section/card surface), `--text:#1d1d1f`,
  `--text-muted:#6e6e73`, `--border:#d2d2d7`, `--accent:#0071e3`, `--accent-hover:#0066cc`,
  `--accent-active:#0058b0`, `--link:#0066cc`, `--focus:#0071e3` (2px ring, 2px offset).
- **REQ-DSG-003** — Status colours: `--success:#1d7a34`, `--warning:#b25000`, `--danger:#d70015`,
  with tinted backgrounds for alerts. Text on every surface keeps contrast >= 4.5:1 (REQ-THM-041).
- **REQ-DSG-004** — Light theme only (changed 2026-10-26 on the owner's request, formerly a dark palette): the stylesheet has no
  `prefers-color-scheme` rules and declares `color-scheme: light`, so the storefront stays light whatever the OS or browser setting.
- **REQ-DSG-005** — Typography: system stack `-apple-system, BlinkMacSystemFont, "Inter", "Segoe UI", Roboto,
  sans-serif`; Inter self-hosted (Latin + Cyrillic subsets, `font-display: swap`) as fallback on non-Apple
  systems (REQ-THM-021). Scale: 12/14/16/19/24/32/48 px, headings weight 600, tight letter-spacing on h1/h2.
- **REQ-DSG-006** — Spacing scale 4/8/12/16/24/32/48/80 px; radius `--r-sm:8px`, `--r-md:12px`, `--r-lg:18px`,
  pill `980px` for primary buttons; one soft shadow `--shadow: 0 4px 24px rgba(0,0,0,.08)`; transition 200 ms
  ease, disabled under `prefers-reduced-motion`.

### Components

- **REQ-DSG-010** — Buttons: primary (accent fill, white text, pill), secondary (outline accent), tertiary (text
  link with chevron); sizes sm/md/lg; hover/active/focus/disabled/loading states.
- **REQ-DSG-011** — Form controls (input, select, textarea, checkbox, radio, quantity stepper): 44 px min
  touch height, 1px border, accent focus ring, visible validation error text.
- **REQ-DSG-012** — Product card (`.product-thumb`, inlined in each listing template; there is no `product/thumb.twig` in ocStore 3.0.3.7): `--bg-alt` surface, large image on neutral background,
  name (2-line clamp), price with old price struck through, add-to-cart button, sticker slots
  (top-left/top-right/bottom-left) provided as empty positioned containers.
- **REQ-DSG-013** — One popup/modal component (`.ocs-modal`) used by every module: overlay, centred card or
  side panel variant, closes on overlay click and Esc, close button, focus trap, body scroll lock, `role="dialog"`
  and `aria-modal`. Implemented once in `window.OCS.modal`; modules must not ship their own.
- **REQ-DSG-014** — Badges/stickers: generic `.ocs-badge` with colour variants driven by CSS variables so the
  stickers module only sets `--badge-bg`/`--badge-fg`.
- **REQ-DSG-015** — Alerts, breadcrumbs, pagination, tabs, accordion, tables restyled with tokens only.

### Layout and pages

- **REQ-DSG-020** — Header: translucent blurred sticky bar (`backdrop-filter`, solid fallback), logo, nav/menu
  slot (mega menu mounts here), search slot, account/wishlist/compare/cart icons with count badges, language and
  currency switchers (uk, ru, en per brief).
- **REQ-DSG-021** — Footer: `--bg-alt` background, multi-column links, muted small text, contacts, language/currency.
- **REQ-DSG-022** — Home: hero banner area, category wall slot, product carousels (featured/latest/bestseller),
  recently-viewed slot; sections separated by whitespace, not borders.
- **REQ-DSG-023** — Category: sidebar slot for OCFilter and side category menu, product grid 4/3/2/1 columns
  (>=1280/>=992/>=576/<576), toolbar for sort/limit.
- **REQ-DSG-024** — Product page: gallery + sticky buy box, options as pill selectors, quick-order and
  pre-order button slots under the add-to-cart button, tabs, related products.
- **REQ-DSG-025** — Blog (ocStore built-in) list/article/category templates styled with the same tokens:
  card list, readable article column (max 72ch).
- **REQ-DSG-026** — Simple (checkout) and OCFilter are skinned only through a dedicated
  `stylesheet/vendor-overrides.css` that maps their classes to tokens; no edits to their files.

### Constraints from the client brief

- **REQ-DSG-030** — Target PHP 7.4 for any PHP in this spec's tasks; Twig/CSS/JS only otherwise.
- **REQ-DSG-031** — All user-visible theme strings exist in uk, ru, en (SPEC-008).
- **REQ-DSG-032** — Module-specific styles live in the module, but consume tokens only; the theme exposes the
  list of tokens and slots in `custom/theme/ocs/DESIGN.md`.

## Non-functional requirements

- **REQ-DSG-040** — Budgets of REQ-THM-042 hold (CSS <= 150 KB, JS <= 60 KB).
- **REQ-DSG-041** — Works in last 2 versions of Chrome, Safari, Firefox, Edge; graceful fallback without
  `backdrop-filter`.
- **REQ-DSG-042** — A client re-brand needs changes to the token block only (documented in `DESIGN.md`).

## Data requirements

None beyond SPEC-006 settings. Image sizes fixed by the design: thumb 600x600, popup 1200x1200, product 400x400,
additional 100x100, category 800x800 (set by a migration, REQ-THM-030).

## Interfaces

CSS tokens, `window.OCS.modal`, slots (`.ocs-slot-*`), `DESIGN.md`, `vendor-overrides.css`.

## Edge cases

- Browser without CSS variables -> unsupported, no fallback.
- Very long product name / missing image -> clamped, neutral placeholder.
- RTL -> not supported in v1.

## Acceptance criteria

- [ ] `DESIGN.md` lists all tokens; no colour literal outside the token block (grep check).
- [ ] Contrast checks pass for all text/background token pairs.
- [ ] Home, category, product, blog pages render at 360/768/1280 px without horizontal scroll.
- [ ] One modal component closes on overlay click and Esc.
- [ ] Lighthouse accessibility >= 90, performance >= 80 on home (manual).
