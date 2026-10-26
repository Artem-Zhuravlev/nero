# PLAN — SPEC-047 Sticky Product Card, Modern Footer, Contacts and Social Settings

## Approach

1. **Side card** — `product.twig`: wrap the sections and a new `<aside class="ocs-side-card">` in a grid (`.ocs-pdp-body`); CSS sticky with the `--head-h` offset (SPEC-038 already
   maintains it); the aside is hidden below 1024 px by a media query. `theme.js` (existing sticky-summary block): the aside button forwards to `#button-cart`; the "in cart" state comes
   from the SPEC-044 `OCS.card` refresh, which already finds buttons by `onclick`/id — the side button carries `data-ocs-buy` and the same `onclick="cart.add(...)"`-style hook is avoided;
   the state is applied by a small selector extension. The top bar is hidden on desktop by CSS when the aside is displayed.
2. **Contacts settings** — theme admin controller/twig/languages: new fieldset with language tabs (pattern of other OpenCart multilingual forms) and a row editor for links
   (vanilla JS in the admin template, no libraries); values saved under `theme_ocs_*` keys (`theme_ocs_contact` = per-language array, `theme_ocs_contact_links` = JSON), validated in
   `validate()`; a pure class `Ocs\Contacts` (parse phones, sanitise links, icon whitelist, href builders) is unit-tested.
3. **Data provider** — `ocs_shell` events read `theme_ocs_contact*` first, fall back to the old `module_ocs_shell_*` values; `apps` dropped. Migration copies the old values once and deletes
   `module_ocs_shell_apps`.
4. **Footer** — `footer.twig` rewritten with the new layout classes; grid in the stylesheet (reuses the SPEC-038 12-column subgrid); `<details>` for the groups with the JS that closes them on phones kept;
   header messenger buttons read the same links list.
5. **Icons** — brand symbols added to the sprite partial; the admin preview uses the same symbols through a small inline sprite in the admin template.
6. **Checks** — tests for `Ocs\Contacts`, for template hooks, settings keys, migration and the side card CSS/markup; browser checks at three widths.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Pinned summary | CSS `position: sticky` aside | no JS positioning, no layout shift |
| Links storage | JSON setting + whitelist | one list for header and footer, easy to validate |
| Icons | built-in sprite symbols | uploads need file handling and sanitising (SVG XSS); out of scope |
| Migration | copy once, read old as fallback | nothing is lost on upgrade, no manual re-entry |

## Affected files / directories

- `custom/theme/ocs/template/product/product.twig`, `common/footer.twig`, `common/header.twig`, `ocs/sprite.twig`, `stylesheet/stylesheet.css`, `javascript/theme.js`
- `custom/extensions/ocs/upload/admin/{controller,view,language}/extension/theme/ocs*`, `system/library/ocs/contacts.php`
- `custom/extensions/ocs_shell/upload/{catalog/controller,admin/...}` and `db`, `custom/db/migrations/0007_contacts_settings.php`
- `scripts/test/contacts.test.mjs`, `scripts/test/php/ContactsTest.php`, `scripts/browser/*`

## Risks

- Admin row editor without a framework -> small, covered by a markup test and a manual run in the admin on the test store.
- Escaping (Twig autoescape off) -> all values pass through `Ocs\Contacts` / `Ocs\Shell::e`; tests include `<script>` and `javascript:` inputs.
- Sticky inside the sections grid needs `align-self: start` and no `overflow` on ancestors -> checked in the browser.
