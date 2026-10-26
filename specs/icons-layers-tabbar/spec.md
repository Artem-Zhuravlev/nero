# SPEC-042 — SVG-Only Icons, Rating Stars, Layer Scale and Bottom Bar

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-26
**Refines:** SPEC-023 (design system), SPEC-031 (header/footer/mobile shell), SPEC-038 (layout), SPEC-039 (SVG sprite)

## Problem

Review of the storefront found three defects and one design rule:

1. Rating stars are drawn with Font Awesome `fa-stack`; they sit shifted and misaligned in product cards and on the product page.
2. The design must not use the Font Awesome icon font at all: only inline SVG icons written in code. Today `fa` classes still appear in
   11 theme templates, 8 module templates, `theme.js`, `categorytree.php` (category icons stored as `fa-*` names) and the stylesheet.
3. z-index values are scattered literals (2, 3, 4, 10, 30, 40, 1005 ... 1070). Blocks and popups cover each other: for example the
   header drop-downs (blog, language, account) are partly hidden behind neighbouring header blocks.
4. The mobile bottom bar has a "Home" link and Font Awesome icons that differ from the header's icons.

## Purpose

One icon system (inline SVG from the sprite), stars that always align, one documented layer scale that every rule uses so that
no block or popup hides another, and a bottom bar that matches the header.

## Scope

Theme `ocs` (templates, stylesheet, `theme.js`, sprite), the `ocs_*` module templates and `Ocs\CategoryTree::icon()`.

## Out of scope

Admin pages (they keep Font Awesome), core templates the theme does not override (checkout, account, information pages): see
Open decision 1. New icons beyond what the current UI uses. Dark mode (removed).

## Functional requirements

### Icons

- **REQ-ICN-001** — No storefront markup owned by the project (theme templates, `ocs_*` templates, strings built in `theme.js`,
  PHP helpers) contains Font Awesome classes (`fa`, `fa-*`). Icons are `<svg class="ocs-i" aria-hidden="true"><use href="#i-name"/></svg>`.
- **REQ-ICN-002** — The sprite gains every missing symbol the UI uses: filled star, list, grid (view switch), exchange/compare,
  calendar, upload, info, alert, check-circle, map pin (exists as `pin`), mobile, headphones, plug and the generic category icons;
  sizes through `.ocs-i` only (`1em` default, modifiers for 16/20/24 px).
- **REQ-ICN-003** — Category/brand icons stored in the database as `fa-*` names keep working: `CategoryTree::icon()` maps each known
  name to a sprite symbol (`fa-mobile` -> `i-mobile`, ...) and falls back to a neutral symbol for unknown names; image paths still render
  as images. The admin form keeps its text field and shows the list of supported names.
- **REQ-ICN-004** — A test fails when a project-owned storefront file contains an `fa`/`fa-` class, and when a `<use href="#i-x">` has no
  matching symbol in the sprite.
- **REQ-ICN-005** — The Font Awesome stylesheet import is removed from the theme stylesheet when no remaining page needs it
  (Open decision 1).

### Rating stars

- **REQ-STR-001** — Stars are an `.ocs-rating` component: five SVG stars in a fixed 1em grid, filled stars drawn by the same SVG with
  the rating colour, empty stars outlined; no `fa-stack`, no inline-block baseline shifts. Used in product cards, product page
  summary, review list and review form (hover/select).
- **REQ-STR-002** — Every rating has an accessible name (`role="img"`, `aria-label` with the value out of 5) and the numeric review count
  stays next to it.
- **REQ-STR-003** — Stars align on one baseline with the review counter in all breakpoints; checked by a browser check at 360 and 1280 px.

### Layer scale

- **REQ-Z-001** — One scale of z-index tokens in the token block of the stylesheet (lowest to highest): `--z-base` (card overlays, slots),
  `--z-raised` (gallery controls, badges), `--z-sticky` (product tab bar, sticky summary), `--z-header`, `--z-dropdown` (header menus,
  mega menu, search results, city popover), `--z-tabbar` (mobile bottom bar), `--z-overlay` (modal overlay and dialogs, cart panel),
  `--z-toast`. Implemented order: base < raised < pop < pop-top < sticky < tabbar < header < overlay < toast, where `--z-pop` / `--z-pop-top` rank panels
  inside their own stacking context (the header owns its drop-downs, mega menu, search results and city popover). The header sits above the
  bottom bar so the full-screen mobile catalogue covers it; a drop-down is never covered by header siblings; modals cover the header, the bottom bar and
  every drop-down.
- **REQ-Z-002** — No numeric `z-index` literal outside the token block in the theme stylesheet, `theme.js` or module templates (test).
  Local stacking inside a component uses `isolation: isolate` instead of large numbers.
- **REQ-Z-003** — Browser acceptance check (script run in the test store) on home, category, product, search, with each drop-down and each
  popup opened, at 360 and 1280 px: the element under the centre of every open popup/menu panel is the panel itself (via
  `elementFromPoint`), and the modal overlay covers the header and the bottom bar.
- **REQ-Z-004** — Header top-strip drop-downs (blog, language, currency, account, city) open below the strip without being clipped by
  `overflow: hidden` on an ancestor and are not covered by the action row of the header.

### Bottom bar

- **REQ-TAB-001** — The mobile bottom bar has no "Home" link. Items: Catalog, Compare, Cart, Wishlist, Account (the header always shows Compare).
- **REQ-TAB-002** — The bar uses exactly the header's SVG symbols (`i-grid` or the symbol the header's catalog button uses, `i-cart`,
  `i-heart`, `i-user`, `i-compare`), the same stroke weight and counters/badges as the header actions.
- **REQ-TAB-003** — The bar sits on `--z-tabbar`, is hidden while a modal is open, and does not hide page content (bottom padding).

## Non-functional requirements

- **REQ-ICN-020** — Removing the icon font must not cost layout shift: icons have explicit size; no FOUT of missing glyphs.
- **REQ-ICN-021** — Sprite stays under 20 KB; SVG only, no external files.

## Data requirements

No schema change. Category icon values in `ocs_menu_category.icon` stay as they are (REQ-ICN-003 maps them at render time).

## Interfaces

Templates, `stylesheet.css` (tokens + components), `theme.js`, `sprite.twig`, `categorytree.php`, language strings unchanged.

## Edge cases

- Unknown `fa-` name stored for a category -> neutral symbol, never an empty box.
- A module template outside this repo uses `fa` classes -> unaffected only if Font Awesome stays loaded (Open decision 1).
- Rating 0 -> five outlined stars, label "0 / 5", counter hidden when there are no reviews.
- Very narrow screens (320 px) -> bottom bar labels truncate with ellipsis, icons stay 24 px.

## Open decisions (default in bold)

1. Core pages the theme does not override (checkout, account, information) still use `fa` classes. **Keep the Font Awesome stylesheet
   loaded for them only (no project-owned markup uses it)**, or override those templates too (large, separate spec).
2. Bottom bar items: **Catalog, Cart, Wishlist, Account** (+ Compare when shown), or the same four without Compare.

## Implementation notes

- Core templates that render in the shared chrome were overridden with SVG icons (generated from the core files, `@core-file` headers kept):
  `common/language`, `common/currency`, `common/cart`, `blog/menu`, `blog/latest`, `blog/category`, `blog/article`, `blog/review`,
  `product/review`, `product/compare`, `extension/module/{featured,latest,bestseller,special,featured_product,blog_latest,blog_featured,featured_article}`.
  The home breadcrumb icon (text_home from the core language file) is replaced by the SVG in every overridden page template.
- Open decision 1 stands: the Font Awesome stylesheet is still loaded for core pages the theme does not override (checkout, account, information).
- `scripts/browser/check-layers.js` is the REQ-Z-003 overlap check (run in the browser on the test store); it found and now guards the
  header drop-downs covered by the action row and the product code overlapping stickers on cards.

## Acceptance criteria

- [x] `grep -r "fa fa-\|fa-" ` over project-owned storefront files finds nothing; the test for it is green.
- [x] Stars are aligned in cards, product page and review form at 360 and 1280 px (screenshots).
- [x] No numeric z-index outside the token block; the overlap check passes on four pages with every popup open.
- [x] Bottom bar: no Home, header icons, hidden under modals.
- [x] `npm test`, `npm run verify` green; package rebuilt and verified on the test store.
