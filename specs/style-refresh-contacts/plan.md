# PLAN — SPEC-067

1. Tokens: change `--accent*`, `--focus`, `--border` in `stylesheet.css`; add `--btn-h` (46px / 42px under 48rem) and use it in `.btn` / `.btn-lg` min-block-size and header/card action buttons.
2. Font: new `@font-face` Gilroy blocks, `--font` updated, Inter files and README replaced.
3. Placeholders: audit `custom/theme/ocs/template/**` for `<input`/`<textarea` (24 files already contain some), add `placeholder="{{ entry_* }}"`.
4. Contacts: `ocs_shell` event on `catalog/view/information/contact/before` provides `ocs_shell` (same `Contacts::view`); rewrite `contact.twig` with hero + cards + form; CSS block at the end of the stylesheet.
5. `npm run verify`, `npm run pack:theme`, README row, commit `[SPEC-067]`.
