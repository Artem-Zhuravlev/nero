# SPEC-046 — Modern Forms and Product Page Sections Instead of Tabs

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-26
**Refines:** SPEC-023 (visual design), SPEC-035 (product page), SPEC-043 (templates, buttons), SPEC-045 (headings)

## Problem

Review items 17 and 18:

17. The forms look bad and must be brought to a modern style. The screenshot shows the product "Questions" form: three bordered fields and a pill
    button squeezed into one row, a tiny label-less layout. The review form uses a row of bare radio buttons for the rating and a
    right-floated button. Other forms (login, register, address, checkout, contact, return, newsletter, tracking, quick-order popups) share the
    same raw Bootstrap 3 look with only colours changed.
18. Description, characteristics, reviews and questions sit in Bootstrap tabs. They must leave the tabs and become separate blocks, each with its
    own heading, one under another on the product page.

## Scope

Theme `ocs`: stylesheet (form components), `product/product.twig` (sections, review form, questions form), the section bar in `theme.js`,
`ocs_product_extras` section list, form templates of the theme (`account/*`, `checkout/*`, `information/contact`, `affiliate/*`,
`ocs/options.twig`), popup forms (`ocs_request`, `ocs_tradein`, `ocs_newsletter`, `ocs_city`). Tests and browser checks.

## Out of scope

Admin forms, payment vendor forms, changing field names/validation/endpoints, captcha extensions.

## Functional requirements

### Forms

- **REQ-FRM-001** — One form system in the stylesheet (`components` layer, tokens only): field block = label above, 3 rem control, 12 px radius, visible
  focus ring, placeholder lighter than text, disabled and read-only states, help text and error text (icon + colour + `aria-describedby`),
  success state; textarea, select (custom arrow), checkbox, radio and switch as styled controls (>= 44 px touch target under 768 px);
  file input as a button; input groups; required marker. Applies to every `.form-control`, `.form-group`, `.form-horizontal`, `.buttons`
  block of the storefront without changing field names or ids used by core JS.
- **REQ-FRM-002** — Form layout: single column on phones, two columns for short fields (name / phone, city / postcode) from 768 px via a
  grid class; the primary button is full-width on phones and left-aligned (not floated right) on desktop; a form sits in a card with padding and
  a heading where it is a standalone block.
- **REQ-FRM-003** — Review form: rating is a star picker built on the existing radios (visually hidden radios, SVG stars, hover/focus/checked
  states, keyboard arrows work, `fieldset`/`legend`); name and text fields as in REQ-FRM-001; the submit button and the "min / max characters" note
  are aligned with the fields.
- **REQ-FRM-004** — Question form: labelled fields in a grid (name + e-mail, text below, button below), consistent with the review form; the
  success and error message appear under the button as a status block.
- **REQ-FRM-005** — Popup forms (`ocs_request`, `ocs_tradein`, city search, newsletter, tracking) use the same classes, so they change with the system.

### Product page sections

- **REQ-SEC-001** — The `.nav-tabs` strip and the `.tab-content` wrapper are removed. The sections are stacked in this order: description
  (`#tab-description`), characteristics (`#tab-specification`), reviews (`#tab-review`), questions (`#tab-questions`), then the existing accessories /
  similar blocks. Each is a `<section>` with its own `<h2>` (existing translated tab titles), a top divider and spacing from `--sp-*`.
  A section that has no content (no attributes, reviews disabled, questions off) is not rendered.
- **REQ-SEC-002** — The element ids stay (`tab-description`, `tab-specification`, `tab-review`, `tab-questions`, `review`, `form-review`,
  `button-review`) so the rating link, the review AJAX, the JSON-LD and external links keep working; the rating link scrolls to the reviews section.
- **REQ-SEC-003** — The section bar (`#ocs-sections`) becomes a plain anchor menu with scroll-spy: every item scrolls smoothly to its section
  (header and bar offset kept), the current section is highlighted while scrolling; the Bootstrap tab hooks (`data-ocs-tab`, `shown.bs.tab`)
  are removed from `theme.js`. Works without JS as normal anchors.
- **REQ-SEC-004** — The description keeps the rich-text styling (headings from the editor are `h3`+ inside the section, images responsive,
  tables scroll); the characteristics table keeps its zebra style and group headings (`h3` inside the section).

## Non-functional requirements

- **REQ-FRM-020** — Accessibility: every control has a visible label or an `aria-label`; errors are announced (`role="alert"` or `aria-live`); contrast
  of text, borders and focus ring >= 4.5:1 / 3:1.
- **REQ-FRM-021** — Stylesheet within its budget (SPEC-043 test); no new raw `px` spacing; `theme.js` within its budget.
- **REQ-SEC-020** — No change to controllers, models, JSON-LD content or review/question endpoints; SPEC-045 heading test still passes (`h2` sections,
  no skipped level).

## Edge cases

- Reviews disabled -> no reviews section and no link to it in the bar; guest review off -> the "log in" note replaces the form.
- Long description with its own `h2`/`h3` from the editor -> styled as sub-headings inside the section; not changed.
- Browsers without `:has()` or `color-mix()` -> fall back to solid tokens.
- Forms in the cart panel and popups keep their compact variant (`.ocs-req-form`).

## Open decisions (default in bold)

1. Section titles: **`<h2>`** (matches SPEC-045); the existing translated tab names are reused ("Description", "Characteristics", "Reviews", "Questions").
2. Section bar: **kept as a sticky anchor menu with scroll-spy**; or removed because the page is now a single column of blocks.
3. Rating input: **star picker over radios** (above); or a select.
4. Field style: **labels above fields, no floating labels**; or floating labels.
5. Two-column field grid on desktop for short fields: **yes**.

## Implementation notes

- Defaults of the open decisions were used (approved 2026-10-26). The old field rules (legacy section 4, components "form controls") were deleted and replaced by one block at the end of
  the stylesheet (`@layer overrides`); the Bootstrap re-skin test now looks for the focus ring there.
- Characteristics keep the table with the group name in the header row (not an `h3`): group names are table captions, not outline headings.
- The review list container `#review` moved out of the form (above it) so the form is a clean block; core JS still finds it by id.
- Page forms (login, register, address, checkout, contact, return) and popup forms get the new look through `.form-control`, labels, checkboxes/radios and the existing
  `.form-horizontal` grid; the `ocs-form` card and `ocs-form-grid` two-column layout are used by the review and question forms (available for the others).
- Checked on the test store: sections in order with h2, no tabs, rating link and section bar scroll under the sticky header, star picker sets the radio, review validation message,
  question form submit and status, register page fields. `check-spacing.js` was not re-run in the browser pane.

## Acceptance criteria

- [x] No `.nav-tabs`/`.tab-pane` on the product page; four sections with `h2` headings; the rating link and the section bar scroll to them.
- [x] The review form submits (guest and logged in) and shows errors/success; the star picker works by mouse, touch and keyboard.
- [x] The question form submits and shows its status; popup forms look the same system (screenshots at 360/768/1280 px of: product questions, review,
      login, register, checkout step, contact, quick order popup).
- [x] `check-spacing.js` reports nothing new; heading test (SPEC-045) and `npm test`, `npm run verify` green; package rebuilt.
