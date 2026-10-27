# PLAN — SPEC-072 Footer Menu Visibility, Merged Column, Payment Logos

## Approach

1. **Class `Ocs\Footer`** (`custom/extensions/ocs/upload/system/library/ocs/footer.php`, pure, no registry): `sanitize($raw, $validIds)` -> `{hide: [...], payments: 0|1}`; `groups($cfg, $informations, $links, $titles)` -> list of `{key, title, links[{text, href}]}` with hidden links and empty groups removed, texts escaped.
2. **Event data** — the existing `ocs_theme_display` event (`applyDisplay`, route `common/footer`) sets `$data['ocs_foot'] = {groups, payments}`. Information ids come from the `catalog/information` model (rows with `bottom`), the fixed links are already in the footer template data (`contact`, `return`, ...), titles from the footer's own language strings. `extension.json` gets the new setting key.
3. **Template** — `common/footer.twig`: the default branch iterates `ocs_foot.groups` (falls back to the current markup when `ocs_foot` is missing, e.g. event off); the merged group is data, not a template block. The `ocs_shell.groups` branch is left alone. `.ocs-foot-groups` gets `--wide` when there is no contacts column. Bottom row: copyright + `<ul class="ocs-pay">` with two inline SVG logos (Visa wordmark, Mastercard circles) when `ocs_foot.payments`.
4. **CSS** (`@layer overrides`, next to the SPEC-047 footer block): `.ocs-foot-groups--wide` spans all 12 columns from 62rem; the groups grid keeps `auto-fit` so 1–3 columns stretch evenly; `.ocs-foot-bottom` becomes a wrapping flex row, `.ocs-pay` aligned to the end and stacked on phones; logos as fixed-height SVG cards with a light border.
5. **Admin** — controller: `$data['footer_menu']` (links by group with checked state; information pages from the model), sanitising in `validate()` like `top_menu`; twig fieldset with checkboxes and the payments select; language strings uk-ua / en-gb.
6. **Checks** — PHP test `FooterTest.php` for `Ocs\Footer`; Node test for twig/css/language/`extension.json` wiring; browser check of the footer at 1280 / 768 / 360 px with all, some and no links hidden, logos on/off.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Hide by key list | `hide` list, empty by default | new links appear automatically; a "shown" list would hide them after upgrades |
| Merge columns | one data group, not a template block | order and hiding handled in one place |
| Even layout | `auto-fit` grid + empty groups dropped server side | pure CSS cannot drop the heading of an empty column |
| Logos | inline SVG | no extra requests, matches SPEC-042 "SVG-only icons" |
| Custom `ocs_shell` groups | untouched | they are already fully editable text |

## Affected files / directories

- `custom/extensions/ocs/upload/system/library/ocs/footer.php`, `.../catalog/controller/extension/ocs/events.php`, `.../admin/controller/extension/theme/ocs.php`, `.../admin/view/template/extension/theme/ocs.twig`, `.../admin/language/{uk-ua,en-gb}/extension/theme/ocs.php`, `custom/extensions/ocs/extension.json`
- `custom/theme/ocs/template/common/footer.twig`, `custom/theme/ocs/stylesheet/stylesheet.css`
- `scripts/test/php/FooterTest.php`, `scripts/test/footer-menu.test.mjs`, `specs/README.md`, `dist/` (pack:theme)

## Risks

- A hidden page id that is later deleted -> ids are validated against existing pages on save and again when rendering.
- Every link hidden -> contacts and the bottom row still render; no empty grid area (REQ-FMN-004/005).
- Payment marks are trademarks -> simplified own SVG drawings, no raster files.
