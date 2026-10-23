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
