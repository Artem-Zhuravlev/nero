# SPEC-072 — Footer Menu Visibility, Merged Column, Payment Logos

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-047 (modern footer), SPEC-006 (theme settings)

## Problem

The footer always shows every default link (information pages, returns, sitemap, manufacturers, vouchers, affiliate, specials, account links). A store cannot hide the ones it does not use, and the columns "Додатково" and "Особистий кабінет" are two short lists next to each other. There is no sign of the accepted payment systems.

## Purpose

The manager chooses in the theme settings which footer links are shown. The footer stays balanced for any selection: empty columns disappear and the remaining ones share the width evenly. The two short columns become one, and Visa / Mastercard logos are shown at the bottom of the footer.

## Scope

- Theme settings form (`ocs` extension): new fieldset "Footer" with a checkbox per default footer link and a switch for the payment logos.
- Pure class `Ocs\Footer` (sanitise the setting, build the groups), the theme display event feeding `common/footer`.
- `common/footer.twig`, the footer section of `stylesheet.css`, language files (uk-ua, en-gb) of the theme form.

## Out of scope

- Custom footer groups typed in the `ocs_shell` module (`footer_groups`): when they are set they replace the default groups exactly as before and are not affected by the new checkboxes.
- Contacts column, mobile tab bar, `core/`.
- Editable payment system list (only Visa and Mastercard), payment gateways themselves.

## Functional requirements

- **REQ-FMN-001** — The setting `theme_ocs_footer` holds `hide` (list of link keys) and `payments` (0/1). Keys: `info:<id>` for every information page shown in the footer, and `contact`, `return`, `sitemap`, `manufacturer`, `voucher`, `affiliate`, `special`, `account`, `order`, `wishlist`, `newsletter`. Unknown keys and ids of missing pages are dropped on save. A missing setting means: nothing hidden, logos shown.
- **REQ-FMN-002** — The theme form has a "Footer" fieldset with one checkbox per link (checked = shown), grouped like the footer columns, and a switch "Payment logos".
- **REQ-FMN-003** — The default footer has three link groups: Information (information pages), Customer service (contact, return, sitemap) and one merged group "Додатково" (manufacturer, voucher, affiliate, special, then account, order, wishlist, newsletter). The former "Особистий кабінет" column no longer exists.
- **REQ-FMN-004** — Hidden links are not rendered. A group with no visible links is not rendered at all (no heading, no empty column).
- **REQ-FMN-005** — The footer looks right for every selection: the visible groups share the width of the link area evenly (one, two, three columns), and when the contacts column is absent the groups use the full width instead of leaving an empty area.
- **REQ-FMN-006** — With the switch on, the footer bottom row shows inline SVG logos of Visa and Mastercard (accessible names, no external images) next to the copyright line; on narrow screens they stack under it. With the switch off nothing is rendered.
- **REQ-FMN-007** — Strings of the new fieldset exist in uk-ua and en-gb.

## Non-functional requirements

- **REQ-FMN-008** — `core/` untouched; no new tables; the setting is registered in `extension.json` (settings list).
- **REQ-FMN-009** — Output escaped in PHP (Twig autoescape is off). `npm run verify` green; theme archive rebuilt (`npm run pack:theme`).

## Acceptance criteria

- [ ] Unchecking "Sitemap" in the form removes it from the footer; unchecking all of "Customer service" removes the whole column.
- [ ] Default footer shows three columns (Information, Customer service, Додатково) with no "Особистий кабінет" column.
- [ ] 1, 2 and 3 visible columns are evenly spread at 1280 px with no empty gap; the phone layout (accordion) has no empty sections.
- [ ] Visa and Mastercard logos show in the bottom row and disappear with the switch off.
- [ ] A footer with `ocs_shell` custom groups is unchanged.
