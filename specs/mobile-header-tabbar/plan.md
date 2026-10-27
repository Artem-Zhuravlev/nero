# PLAN — SPEC-059 Mobile Header Reduction and Tab Bar Order

## Approach

CSS hides `#top.ocs-util` and `.ocs-head-actions` at <= 767px and changes the head grid to logo / search / catalogue.
A small `theme.js` module wraps the util content in one `.ocs-mnav-extra` container inside `.navbar-ex1-collapse`
(created when the mega menu exists) and, with `matchMedia('(max-width: 767px)')`, moves the existing nodes (`.ocs-top`, `.ocs-strip`,
`.ocs-contact`, `#form-language` wrapper, `#form-currency` wrapper) into it on mobile and back into `#top .container` on desktop.
The tab bar in `footer.twig` is reordered; the cart tab gets a `data-count` fed by the existing cart observer in `theme.js`.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Info/language/currency placement | Move existing nodes with JS | Duplicating markup would duplicate form ids (`#form-language`, `#form-currency`) used by `common.js`; the mega menu is rendered by another controller, so the header template cannot place nodes inside it |
| Header actions on mobile | Hidden with CSS, tab bar replaces them | Tab bar already has account/cart/compare/wishlist |
| Cart badge | Reuse `digits()`/observer of SPEC-050 | One source of truth for the count |

## Affected files / directories

- `custom/theme/ocs/template/common/footer.twig`, `header.twig` (hook class only if needed)
- `custom/theme/ocs/stylesheet/stylesheet.css`
- `custom/theme/ocs/javascript/theme.js`
- `dist/ocs-theme-<VERSION>.ocmod.zip` (rebuilt with `npm run pack:theme`)

## Risks

- Moved dropdowns clipped by the scrolling panel (`overflow-y: auto`) -> render language/currency as inline lists in the panel, not dropdowns.
- Bootstrap dropdown handlers lost on move -> nodes are moved, not cloned, so delegated handlers survive.
