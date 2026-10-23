# PLAN — SPEC-023 Storefront Visual Design

## Approach

Build on SPEC-006: first land the registration/header/asset pipeline (TASK-THM-001..006), then add a token layer
and components in a single committed `stylesheet.css` (optional SCSS input), then override only the templates
listed in REQ-THM-050 for visual markup. Modal and slots come first because every later module depends on them.
Apple.com is used only as a colour/spacing reference.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Styling | Plain CSS custom properties, Bootstrap 3 kept (core JS needs it) | Tailwind/Bootstrap 5 would break core DOM hooks |
| Dark mode | `prefers-color-scheme` + same tokens | No toggle in v1; less JS |
| Fonts | System stack + self-hosted Inter | SF Pro is not licensable for web; CDN forbidden (REQ-THM-021) |
| Third-party modules | Skin via `vendor-overrides.css` | Never edit OCFilter/Simple |
| Modules | One spec per module after this spec | Brief lists ~10 modules; too large for one spec |

## Affected files / directories

- `custom/theme/ocs/stylesheet/{tokens,base,components,pages,vendor-overrides}.css` (concatenated into `stylesheet.css`)
- `custom/theme/ocs/template/common/{header,footer,home,menu}.twig`, `product/{product,category,thumb}.twig`, `blog/*`
- `custom/theme/ocs/javascript/theme.js` (`OCS.modal`), `custom/theme/ocs/fonts/`, `DESIGN.md`
- `custom/db/migrations/` (image sizes), `specs/README.md`

## Risks

- Bootstrap 3 + custom tokens drift -> all colours via variables, grep check.
- Third-party module markup unknown until installed -> vendor-overrides finalised after install.
- Dark mode contrast regressions -> automated contrast test over token pairs.

## Proposed follow-up specs (from the client brief, not part of this one)

SPEC-024 quick order + pre-order + back-in-stock; SPEC-025 AJAX cart; SPEC-026 mega menu + side menu + category
wall; SPEC-027 AJAX search; SPEC-028 stickers; SPEC-029 recently viewed. Buy, do not build: OCFilter, Simple.
