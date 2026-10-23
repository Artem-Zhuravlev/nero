# Theme ocs - design guide (SPEC-023)

## Re-brand

Edit only section 1 ("Design tokens") of `stylesheet/stylesheet.css`. Nothing else may contain a colour literal
(`npm test` enforces it). Dark mode redefines the same tokens in the `prefers-color-scheme: dark` block; every
text/background pair must keep contrast >= 4.5:1 (also tested).

## Tokens

| Group | Tokens |
| ----- | ------ |
| Surfaces / text | `--bg`, `--bg-alt`, `--text`, `--text-muted`, `--border`, `--overlay`, `--header-bg` |
| Accent | `--accent`, `--accent-hover`, `--accent-active`, `--on-accent`, `--link`, `--focus` |
| Status | `--success`, `--warning`, `--danger` and `--*-bg` tints |
| Badge | `--badge-bg`, `--badge-fg` (the only two a stickers module sets) |
| Type | `--font`, `--fs-xs` .. `--fs-3xl` |
| Space / shape / motion | `--sp-1` .. `--sp-8`, `--r-sm`, `--r-md`, `--r-lg`, `--r-pill`, `--shadow`, `--t` |

## Slots for modules

- Product card sticker containers: `.ocs-slot.ocs-slot-tl | -tr | -bl` (absolute inside `.product-thumb`), badges are `.ocs-badge`.
- Header: mega menu mounts at `{{ menu }}`, search at `{{ search }}`, cart at `{{ cart }}`.

## Popups

Every popup uses `OCS.modal` (`javascript/theme.js`); modules must not ship their own.

```js
OCS.modal.open({ content: htmlOrElement, variant: 'center' /* or 'side' */, closeLabel: text, labelledBy: id, onClose: fn });
OCS.modal.close();
```

Closes on overlay click, Esc and the close button; traps focus; restores focus to the opener. `closeLabel` comes from
the module's language file (no literal text in the theme).

## Layout layer (SPEC-038)

The stylesheet is written in cascade layers: `reset` (Bootstrap 3 and Swiper CSS, imported with `layer(reset)`), `tokens`, `legacy`
(sections written before SPEC-038, shrinking), `base`, `layout`, `components`, `utilities`, `overrides`. A later layer always wins, so
no `!important` is needed (only the reduced-motion and `[hidden]` guards keep it).

- **Shell:** `body` is a grid (`auto auto 1fr auto`, one more row with the announcement bar); the page content sits in `.ocs-page`
  (opened by `common/header.twig`, closed by `common/footer.twig`); `.container` is `min(100% - 2 * var(--gutter), var(--container))`.
- **Containers, not viewports:** `header.ocs-head` (`head`), `.ocs-page > .container` (`page`), `.product-layout` (`card`),
  `#common-home #content` (`home`), `#product-product #content` (`pdpc`), `footer` (`foot`), `.ocs-modal-dialog` (`dialog`).
  Components answer `@container` rules of their own container, so the same card works in a listing, a carousel and a popup.
- **Subgrid:** listing cards span 9 rows of the listing grid and take them over with `grid-template-rows: subgrid`; every part
  (image, code, title, text, stock, price, rating, quick order, buttons) is placed in an explicit row, so rows line up without
  fixed heights. The footer sections take over the 12 footer tracks with `grid-template-columns: subgrid`. Both sit behind `@supports`.
- **`:has()`:** sidebar columns, the sticky listing toolbar and the quick-view dialog size are chosen by what the markup contains.
- **Units:** rem (the root is back to 16px), `clamp()` for the type and gutter scale, `dvh`, `aspect-ratio`, logical properties,
  `color-mix()` for hover and surface tones (no new colour literals outside section 1).
- **Sticky offsets:** `--head-h` is set from the real header height (0 when the header scrolls away on phones).
