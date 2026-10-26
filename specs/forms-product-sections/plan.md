# PLAN — SPEC-046 Modern Forms and Product Page Sections Instead of Tabs

## Approach

1. **Form system** — a new block of tokens-only CSS (`@layer components`) for fields, labels, help/error/success, select/checkbox/radio/switch, file input,
   input groups, button rows, form card and the 2-column grid. It replaces the scattered `.form-control` rules (lines of sections 1211-1230 and 1625+
   of the stylesheet) so there is one source. Field names/ids untouched; error messages from core JS (`.text-danger`, `.has-error`) get styled.
2. **Star picker** — pure CSS over the existing `input[type=radio][name=rating]` (visually hidden radios + `label` with the SVG star sprite);
   markup of the review form changes only around the radios (fieldset + labels).
3. **Sections** — `product.twig`: replace `ul.nav-tabs` + `.tab-content` with `<section class="ocs-section" id="tab-...">` blocks, rendered only
   when they have content; the bar in `theme.js` loses the tab branch and observes all sections for scroll-spy. `ocs_product_extras` section list keeps
   `href`s; its `tab` flag is ignored (kept in data for compatibility).
4. **Other forms** — templates of the theme and popup extensions get the classes/wrappers of the system (grid, button row, card); no behaviour change.
5. **Checks** — tests for the new markup (no tabs, ids kept, headings), the form CSS tokens, the star picker markup; `check-spacing.js` on the form pages;
   screenshots at three widths.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Rating input | styled radios | keeps the posted field (`rating`) and core review JS unchanged |
| Sections | stacked `<section>` + anchor bar | requirement; tabs hide content from users and crawlers |
| Form CSS | one block in `components` | replaces ad-hoc rules, easier to keep consistent |

## Affected files / directories

- `custom/theme/ocs/stylesheet/stylesheet.css`, `custom/theme/ocs/javascript/theme.js`
- `custom/theme/ocs/template/product/product.twig`, `account/*.twig`, `checkout/*.twig`, `information/contact.twig`, `affiliate/*.twig`, `ocs/options.twig`
  (generated templates that get edits leave the regenerate list, as in SPEC-044/045)
- `custom/extensions/ocs_product_extras/.../events.php` (section data), popup form templates of `ocs_request`, `ocs_tradein`, `ocs_newsletter`, `ocs_city`
- `scripts/test/forms.test.mjs`, `scripts/browser/check-spacing.js`

## Risks

- Core JS that opens the tab for reviews (`$('a[href="#tab-review"]').trigger('click')`) -> replaced by an anchor handler; ids kept.
- Editor content with inline styles inside the description -> contained by the section's `overflow` and responsive images.
- Large CSS growth -> the old form rules are deleted, not stacked; the stylesheet budget test guards it.
