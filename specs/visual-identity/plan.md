# PLAN — SPEC-039 Visual Identity Pass

## Approach

Work page by page with the live stand and the reference open side by side, in this order: foundations (font, tokens, base
components, icon sprite) -> card -> header -> home -> category -> product -> motion/states -> demo seed. Each step ends with
screenshots at 1440 and 375 px.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Font | Self-hosted Inter variable | No third-party request, GDPR-friendly; system font alone cannot give the weight range |
| Icons | Inline SVG sprite in `header.twig` | Crisp, themeable by `currentColor`, no icon font request |
| Counters | JS mirrors the core counter text into badges | Core updates counters as text inside the link |
| Home category menu | The mega-menu panel rendered in place by CSS | Reuses markup and behaviour of SPEC-027 |
| Shelf tabs | One module instance with several category tabs, server-rendered panels | No extra request per tab; works without JS as a stacked list |

## Affected files / directories

- `custom/theme/ocs/**` (stylesheet, templates, `theme.js`, `fonts/`), home block extensions' templates, `scripts/seed-demo.php`
- tests in `scripts/test/theme.test.mjs`, `custom/theme/ocs/DESIGN.md`

## Risks

- Scope: kept in verifiable steps, each with a commit-sized result.
- Font licence: Inter is OFL; the licence file ships with the font.
