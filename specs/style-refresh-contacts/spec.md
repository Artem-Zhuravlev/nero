# SPEC-067 — Style Refresh (Black Accent, Taller Buttons, Gilroy) and Contacts Page

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-006, SPEC-039, SPEC-047

## Problem

The accent is blue, buttons are 40px, grey borders are dark, the font is Inter, form fields have no placeholders and `information/contact` is the default OpenCart page.

## Purpose

Restyle the theme (black accent, larger buttons, light borders, Gilroy) and make the contacts page a designed page that shows the shop contacts configured in the theme settings.

## Scope

Theme `ocs`: tokens/stylesheet, fonts, form templates (input/textarea placeholders), `information/contact.twig`; `ocs_shell` event feeding contact data to the contact page.

## Out of scope

Admin panel, new settings fields, new languages.

## Functional requirements

- **REQ-REF-001** — `--accent` is black (`#000000`); hover/active/focus derive from it (`#1d1d1f`, `#333333`); `--on-accent` stays white. Every `#0071e3` in the theme stylesheet is replaced by the token.
- **REQ-REF-002** — Standard buttons (`.btn`, `.btn-lg`, header/card/cart action buttons) are 46px high on desktop (>= 48rem) and 42px on phones; `.btn-sm` / icon buttons keep their size.
- **REQ-REF-003** — `--border` is `#f3f3f3`; derived `color-mix` borders stay readable.
- **REQ-REF-004** — The font is Gilroy (weights 400/500/600/700/800, latin + cyrillic) from `theme/ocs/fonts/`; `--font` lists Gilroy first with the existing system fallback. Inter files are removed.
- **REQ-REF-005** — Every `<input>` (text, email, tel, password, number, search) and `<textarea>` in theme templates has a meaningful `placeholder` (from the existing `entry_*` language strings, no new hard-coded text).
- **REQ-REF-006** — `information/contact` is a designed page: header block, cards for address (+map button), phones (tap-to-call), working hours, messengers/social links with icons, then the enquiry form in a card. Data comes from the theme contact settings (`theme_ocs_contact`, `theme_ocs_contact_links`) with fallback to the core store fields (address, telephone, open). Layout is a two-column grid on desktop, one column on phones.
- **REQ-REF-007** — Additional store locations (`locations`) stay available as cards below the main block.

## Open questions

1. Border `#f3f3f3` on white is almost invisible; for form fields/inputs I will use `--border` as asked, unless you want a darker input border.
2. **Gilroy is a commercial font and is not in the repo.** Please provide the licensed `.woff2` files (Regular/Medium/SemiBold/Bold/ExtraBold, ideally latin + cyrillic). Until then CSS references the expected file names and falls back to system fonts.

## Acceptance criteria

- [x] No blue left on the site; primary buttons, links accents, focus are black.
- [x] Buttons 46px desktop / 42px at 375px.
- [x] Gilroy loads (once files are provided); no request to Inter.
- [x] All forms show placeholders.
- [x] Contacts page shows address, phones, hours, messengers/social from settings; form works; mobile layout is single column.
- [x] `npm run verify` green, theme archive rebuilt.
