# SPEC-047 — Sticky Product Card, Modern Footer, Contacts and Social Settings

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-26
**Refines:** SPEC-031 (header, footer, mobile shell), SPEC-035/039 (product page, sticky summary), SPEC-006 (theme settings), SPEC-042 (SVG icons), SPEC-044 (stock/SKU switches)

## Problem

Review items 19 and 20 (item 21 was cut off in the screenshot and is not covered):

19. The product card (photo, badges, title, price, buy button) must be pinned to the right of the long sections (description, characteristics,
    reviews, questions), as on the reference screenshot. Today only a thin bar appears at the top of the page after the buy button scrolls away.
20. The footer is the default one. It must get a modern design with: social networks and messengers (icons), the shop address, clickable phone
    numbers and the working hours. Messengers and networks are set in the admin, but there is no way to choose an icon for a network or messenger;
    address, phones and hours must be multilingual fields in the **theme settings**. On phones the footer menu groups are collapsed in `<details>`.
    The "app" links ("Додаток") are removed from the footer (and from the product page, see open decision 4).

## Scope

Theme `ocs`: `product/product.twig` (side card), `theme.js`, stylesheet, SVG sprite (brand icons), `common/footer.twig`, `common/header.twig` (messenger links use the same
list), theme settings form (admin) and its languages, `ocs_shell` data provider and admin form (apps removed, links list), migration of existing values, tests, browser checks.

## Out of scope

Payment-method logos, newsletter block (SPEC-033), the mobile bottom bar, uploading custom icon files.

## Functional requirements

### Sticky product card (item 19)

- **REQ-SIDE-001** — From 1024 px the product sections (`.ocs-pdp-sections`) become a two-column grid: the sections on the left, an `<aside class="ocs-side-card">`
  on the right (sticky under the header: `top = --head-h + --sp-4`, stays inside the sections block, never overlaps the footer). Below 1024 px the card is not shown and
  the existing top sticky bar (SPEC-039) keeps working.
- **REQ-SIDE-002** — The card shows: photo (the product image, lazy), badges of the product stickers, brand link, title (not a heading: `<p class="ocs-side-title">`,
  the page already has its `h1`), price (old / current, SPEC-045 markup rules: no heading), stock label (when `show_stock` is on, SPEC-044), the buy button, wishlist and compare icon
  buttons. The buy button repeats the page button (`#button-cart`): it clicks it, shows the "in cart" state of SPEC-044 and the pre-order / out-of-stock variants of the page.
- **REQ-SIDE-003** — On desktop the top sticky bar is hidden while the side card is visible (one pinned summary at a time).
- **REQ-SIDE-004** — The side card exists only when the product page has at least one section to the left of it (always: description); it carries no personal data and
  is rendered from the same variables as the page (no extra queries).

### Footer (item 20)

- **REQ-FTR-001** — New footer layout: a contacts column (shop name/logo, address, phones, working hours, messenger and social icon buttons) and the link groups
  (the groups configured in `ocs_shell`, else the core information/service/extra/account groups), then a bottom row with the copyright line. From 1024 px: contacts
  column + up to four link columns; 768-1023 px: two columns; below 768 px: one column.
- **REQ-FTR-002** — Phones are `<a href="tel:+380...">` links (digits and `+` only in the href, display text as entered); address is plain text (`<address>`); hours is
  text with line breaks kept; every field is multilingual (value for the current language, fallback to the store's default language).
- **REQ-FTR-003** — On phones (< 768 px) every link group is a closed `<details>` with a `summary` (chevron icon, 44 px target); on wider screens they are open and not
  collapsible (existing behaviour of SPEC-031 kept and covered by a test).
- **REQ-FTR-004** — The "apps" block is removed from the footer, the `module_ocs_shell_apps` setting is removed by migration, and the field disappears from the admin form.

### Settings (theme settings form)

- **REQ-SET-001** — The theme settings form (`extension/theme/ocs`) gets a "Contacts" fieldset with, per store language (tabs): address, working hours, phones (one per line: `number | label`,
  label optional), and a shared (not per-language) list of links.
- **REQ-SET-002** — The links list is a row editor: icon (select from a fixed set with a live SVG preview: telegram, viber, whatsapp, messenger, instagram, facebook, youtube, tiktok,
  x, linkedin, email, phone, map, site), kind (messenger / social), title (per language), URL / handle, sort order, add and remove buttons. Stored as JSON in
  `theme_ocs_contact_links`; values are validated on save (allowed icons only, URL scheme http/https/tg/viber/mailto/tel, length limits).
- **REQ-SET-003** — The footer and the header messenger buttons read these settings; the existing `ocs_shell` fields (phones, hours, address, telegram, viber, messenger, social)
  are copied into the new settings by a migration (once, only when the new ones are empty) and then read as a fallback only, so nothing is lost on upgrade.
- **REQ-SET-004** — Brand icons are added to the SVG sprite (`#i-telegram` ... ), monochrome with `currentColor`, each with an accessible name from the link title.

## Non-functional requirements

- **REQ-SIDE-020** — No layout shift: the side card reserves its column; CSS only (`position: sticky`), JS only for the button states already in SPEC-044; stylesheet and `theme.js` stay in budget.
- **REQ-FTR-020** — Footer links open external networks with `rel="noopener noreferrer"`; icon-only buttons have `aria-label`; contrast >= 4.5:1; heading rules of SPEC-045 hold (footer group titles `h2`).
- **REQ-SET-020** — Output escaping: settings values are escaped for HTML and attributes (Twig autoescape is off in this project); URLs are filtered by scheme.

## Edge cases

- No phones / address / hours / links configured -> the corresponding block is not rendered, no empty headings.
- A product without a photo -> the card shows a placeholder image box of the same size.
- A very long title -> clamped to three lines. A product with required options -> the side buy button scrolls to the options when the page button would redirect.
- Language without translated contacts -> the default language values are shown.
- RTL languages -> out of scope.

## Open decisions (default in bold)

1. Desktop pinned summary: **side card replaces the top sticky bar from 1024 px**; or both are shown.
2. Where the contacts are edited: **in the theme settings form (new fieldset), the old `ocs_shell` fields stay as read-only fallback**; or keep them in the module and only add icons there.
3. Icons: **a fixed built-in set of 14 SVG icons chosen from a list**; or uploadable custom icons.
4. "Додаток" on the product page: no app block exists in the theme code (only the footer app links). **Default: remove only the footer app links**; tell me which product block you mean
   (accessories? a third-party widget on the live site?) and I will add it to this spec.
5. Footer layout: **contacts column on the left + link groups on the right**; or contacts in a separate row above the link groups.

## Implementation notes

- Defaults of the open decisions were used (approved 2026-10-26). "Додаток" on the product page: no such block exists in the theme, so only the footer app links were removed
  (the `module_ocs_shell_apps` setting is deleted by migration 0007).
- Settings: `theme_ocs_contact` (per language code: address, hours, phones) and `theme_ocs_contact_links` are stored as serialized arrays (not a JSON string); the admin posts them,
  `Ocs\Contacts::sanitize` whitelists icons and URL schemes on save. `Ocs\Contacts::view` builds the storefront data; `ocs_shell` uses it when any contact value is set, otherwise the old module fields.
- The admin row editor shows a live icon preview from a small inline sprite. The admin template was rendered with Twig against sample data (12 inputs, icon preselected); the editor was not exercised in a logged-in admin session.
- The footer is one layout for both cases (module on / off): contacts column when there is something to show, link groups as `<details>` (open and not collapsible from 768 px, closed on phones; `theme.js` follows the breakpoint).
- The side card sticks at `--head-h + 3.25rem + --sp-4` so it stays under the sticky section bar; its buy button forwards to the page button (pre-order / back-in-stock variants follow the page) and gets the SPEC-044 "in cart" state.
- Verified on the test store: footer at 1024 and 375 px (clickable phones, five icon buttons, collapsed groups, no horizontal scroll), the pinned card beside the reviews section below the bar, top bar hidden on desktop.

## Acceptance criteria

- [x] At 1280 px the card is pinned right of the sections while scrolling description to questions and stops before the footer; at 1023 px it is gone and the top bar works.
- [x] The card's buy button adds the product, shows "in cart", works for pre-order / out-of-stock products; wishlist and compare icons show their active state.
- [x] Footer shows address, clickable phones, hours and icon buttons from the admin (per language); apps are gone; phone widths show collapsed `<details>`.
- [x] Admin: the contacts fieldset saves, validates and keeps rows; a migrated shop shows its old phones/links.
- [x] `npm test`, `npm run verify` green; package rebuilt; screenshots at 360/768/1280 px reviewed.
