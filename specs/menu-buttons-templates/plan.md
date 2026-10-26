# PLAN — SPEC-043 Mobile Menu, Button Metrics and Full Template Set

## Approach

1. Menu: raise `--z-menu/--z-overlay/--z-toast` to the top range, keep the panel outside any containing block, make the bar one `<button>`
   (label + icon inside, `aria-expanded`), lock scroll, close on Esc and link click; extend `check-layers.js` with a simulated third-party
   `z-index: 5000000` fixed element.
2. Buttons: one flex rule for `.btn` and theme buttons in the last components layer (line-height from the scale, `gap: var(--gap-icon)`),
   remove per-button `line-height`/padding hacks found by the audit; `check-spacing.js` measures text-centre offset, icon gaps, targets,
   padding proportions and horizontal overflow on 8 pages at 360 and 1280 px; fix what it reports.
3. Templates: `scripts/gen-theme-templates.mjs` (generalisation of the SPEC-042 generator) writes every missing override from the core file,
   then page-group CSS is added and reviewed with screenshots; a test checks each override keeps the core file's Twig variables and ids.
4. Smoke the account and checkout flows on the test store; rebuild the package.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Menu above third-party popups | top-range z-index token | The `<dialog>`/popover top layer is not supported on all target browsers; a very high root-level z-index works everywhere |
| Button centring | flex + line-height from scale | Padding-based centring depends on font metrics (Inter) and Bootstrap's 1.43 line-height |
| Templates | generated from core with converters | ~80 hand copies would drift; the generator documents every change |
| Payment vendor templates | not overridden | Owned by payment extensions, change with each vendor release |

## Affected files / directories

`custom/theme/ocs/stylesheet/stylesheet.css`, `javascript/theme.js`, `template/**` (about 80 new files),
`custom/extensions/ocs_mega_menu/.../ocs_mega_menu.twig`, `scripts/gen-theme-templates.mjs`, `scripts/browser/check-{layers,spacing}.js`,
`scripts/test/theme.test.mjs`, docs.

## Risks

- Generated overrides break a core JS hook (ids/classes) -> test of variables/ids plus smoke flows.
- Forms and tables restyled with CSS can break layout of rarely seen pages -> screenshot review per group.
- A very high z-index can hide the browser's own UI hints -> limited to the menu, modals and toasts.
