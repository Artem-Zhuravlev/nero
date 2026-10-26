# SPEC-049 — Product Code with Copy, Review/Question Popups with Pros and Cons, Contact Icons, Inline Language Switch

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-26
**Refines:** SPEC-035/046 (product page, forms), SPEC-047 (contacts, footer), SPEC-031 (header), SPEC-044 (SKU switch), SPEC-045 (headings)

## Problem

Review items 24-27:

24. The product page must show the product code with a "copy" button (reference: a pill "Код товару: 558059 [copy icon]" near the title).
25. The review and the question forms open in a **popup** (modal). In reviews the customer can add **pros and cons** (fields "+ Переваги", "- Недоліки") besides the star rating and the text.
    (The reference also shows photo/video upload; it is not requested in the text and is out of scope.)
26. Social networks and messengers are shown as **icon + text**, not as plain text: in the header "Contact us" dropdown (round coloured icon + title, phones included) and in the footer.
27. The language is switched not through a dropdown but as inline text links: "Укр | Рос".

## Scope

Theme `ocs` templates (`product/product.twig`, `common/header.twig`, `common/footer.twig`, `common/language.twig`, `product/review.twig`), `theme.js`, stylesheet, `ocs_product_extras`
(review extra table, events for saving and showing pros/cons, language strings), admin view of pros/cons (read-only), tests.

## Out of scope

Photo/video upload in reviews, review moderation changes, rating logic, a new language-detection scheme, the currency switcher.

## Functional requirements

### Product code (item 24)

- **REQ-COD-001** — A pill "<label>: <code> [copy button]" is shown in the product info column under the title row; the code is the product model (`model`), the same value the SKU switch of SPEC-044 controls
  (switch off -> no pill). The button copies the code with `navigator.clipboard.writeText` (fallback: a hidden textarea and `execCommand('copy')`), changes to a check icon for 1.5 s and announces "copied" through an
  `aria-live` region; it is `type="button"` with an `aria-label`.
- **REQ-COD-002** — The same pill is shown in the side card of SPEC-047. The code text is selectable, escaped, and not a heading.

### Review and question popups (item 25)

- **REQ-REV-001** — The review and question sections keep their lists; the inline forms are replaced by buttons "Write a review" / "Ask a question". The rating link of the info column and these buttons open the forms in
  `OCS.modal` (centre variant): title, close button, Esc, focus trap (existing behaviour). The form markup stays in the page inside a hidden container and is moved into the popup and back on close, so ids and the core review JS
  (`#form-review`, `#button-review`, `#review`) keep working; without JS the section anchor still shows the form.
- **REQ-REV-002** — The review popup has: overall rating (the star picker of SPEC-046, inline with its label), a "+ Pros" input and a "- Cons" input (single line, 255 characters each, optional), the review text, the name (guests), the captcha when enabled,
  buttons "Cancel" / "Send". Validation messages appear under the form; after success the popup shows the message and closes after a short delay (the review list is reloaded as in core).
- **REQ-REV-003** — Pros and cons are stored in `oc_ocs_review_extra (review_id, pros, cons)` by an after-event of `catalog/model/catalog/review/addReview` (the core controller and its validation are unchanged), cut to 255 characters, tags stripped,
  escaped on output. Published reviews show them in the list as two labelled lines with a green "+" and a red "-" icon, only when filled in.
- **REQ-REV-004** — The question popup holds the existing question form (SPEC-046 REQ-FRM-004) unchanged in behaviour (token, honeypot, rate limit, status block).
- **REQ-REV-005** — The admin review form shows pros and cons read-only (through the admin events of `ocs_product_extras`), deleting a review deletes its extra row.

### Contact icons (item 26)

- **REQ-CNT-001** — The header "Contact us" dropdown lists phones and links as rows: a round brand-coloured icon chip + title (Telegram blue, Viber purple, Messenger blue-violet, WhatsApp green, phone green; other icons neutral) + the number or handle; the whole row is the link
  (44 px target). Working hours stay as the last row.
- **REQ-CNT-002** — The footer contacts column lists messengers and social networks as icon + text rows (the icon-only round buttons of SPEC-047 are replaced; one column on phones, two from 768 px). Titles come from the theme settings (SPEC-047); the icon colours are shared with the header.
- **REQ-CNT-003** — Colours are tokens (`--brand-telegram` ...), the white glyph on the chip has AA contrast; no new icons are needed (SPEC-047 set).

### Language switch (item 27)

- **REQ-LNG-001** — `common/language.twig` shows the languages inline: each language is a text link/button with the first three letters of its name ("Укр", "Рос") separated by a thin divider; the current one is bold and not a link (`aria-current="true"`).
  No dropdown, no flag images, no chevron. The form, the hidden `code` / `redirect` inputs and the `.language-select` hook of core JS stay (switching posts the form as before). With one language nothing is rendered. Works at 360 px in the utility bar.
- **REQ-LNG-002** — The short label is derived from the language name (first three characters, UTF-8 safe); the full name is the `title` / `aria-label`. The store's language order is kept.

## Non-functional requirements

- **REQ-REV-020** — The new table has its own migration (`ocs_product_extras/003_review_extra.php`); every read of it is guarded so a store that has not run the migration still shows the product page, the review list and the forms (lesson of SPEC-048);
  a test runs the handlers without the table.
- **REQ-REV-021** — No new library; `theme.js` stays within its budget (raise with a note if needed); heading rules of SPEC-045 hold (popup titles are `h2` inside the dialog only).
- **REQ-CNT-020** — External links carry `rel="noopener noreferrer"`; no secrets or personal data in the markup.

## Edge cases

- Clipboard blocked (http, old browser) -> fallback; if it fails the code stays selectable and the button shows nothing misleading.
- A review with only pros or only cons is accepted; both empty -> no extra row.
- Guest reviews disabled -> the popup shows the login note instead of the form.
- Captcha enabled -> rendered in the popup form as before.
- One language only -> no switcher; a language name shorter than three characters is used as is.

## Open decisions (default in bold)

1. The shown "code" is **the product model** (SKU); or `product_id`.
2. The review popup opens from **both the section button and the rating link**; the inline forms are removed from the page.
3. Pros/cons are **single-line fields** (as in the reference); or multi-line.
4. Footer messengers/social: **icon + text rows**; or keep the round icon buttons and add text only on wide screens.
5. Language labels: **first three letters of the language name**; or a setting per language.

## Implementation notes

- Defaults of the open decisions were used (approved 2026-10-26).
- The save event cannot use `getLastId()`: the core model runs SELECTs after its INSERT (mail alert), so the id is 0. The new review is looked up again by product, customer, author and text. This was found by posting a real review to the test store, not by a static test.
- Without JS the form boxes stay visible in their sections (`html.ocs-js` is set by `theme.js` and hides the boxes and shows the opener buttons). The popup moves the box content into `OCS.modal` and back on close.
- The utility bar (before the sticky header in the DOM) got `z-index: --z-header + 1`: its dropdown (contacts, currency) was painted over by the header and the section bar.
- Copy feedback could not be exercised in the automation pane: the Clipboard API and `execCommand` both refuse there (the page is not focused). The code path is covered by the markup/handler test and falls back silently; check it once in a real browser.
- The admin review form shows pros and cons through the new OCMOD (`ocs_review_extra`, installed by migration 003; modifications must be refreshed once). Not opened in a logged-in admin.
- The language label is the first three characters of the language name ("Укр", "Eng"); languages named with the admin language names of the store.
- Checked on the test store: pill, popup open / send / close / form returned to its box, review stored with pros and cons (tags stripped) and shown in the list, header dropdown rows, footer rows, inline language switch. Tests run for the touched areas only.

## Acceptance criteria

- [x] The code pill shows the model and copies it (check icon feedback); with the SKU switch off it is not rendered.
- [x] "Write a review" and "Ask a question" open popups; a review with pros/cons is saved and shows in the list with its labelled lines; core validation messages work; guests without the right see the login note.
- [x] Header dropdown and footer show coloured icon + text rows for the configured messengers, phones and networks.
- [x] Language is "Укр | Рос" inline; switching works and keeps the page; one language shows nothing.
- [x] The product page still renders completely on a database without the new table (tested); tests of the touched areas and a product page smoke pass; package rebuilt.
