# PLAN — SPEC-055 Mega Menu: Full-Height Flyout and Banner Column

## Approach

Make `.ocs-mega-l1` the containing block on desktop: remove `position: relative` from `.ocs-mega-item` for
>= 768px and position `.ocs-mega-item.open > .ocs-mega-fly` with `top: 0; bottom: 0; left: 100%`, flex row
(body + banner). Twig wraps cols/brands/see-all in `.ocs-mega-body`. Controller requests the banner at 480x720.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Height source | CSS absolute against the list | No JS measuring; JS-height would flicker |
| Banner position | flex column inside the flyout | Matches the reference; keeps one hover target |
| Banner data | existing `ocs_menu_category` fields | No migration needed |

## Affected files

- `custom/theme/ocs/stylesheet/stylesheet.css` (section 13)
- `custom/extensions/ocs_mega_menu/upload/catalog/view/theme/default/template/extension/module/ocs_mega_menu.twig`
- `custom/extensions/ocs_mega_menu/upload/catalog/controller/extension/module/ocs_mega_menu.php`
- node test in `scripts/test/`

## Risks

- Items with `position: relative` are used by the mobile expand button (`.ocs-mega-expand` is absolute) -> keep relative inside the mobile media query only.
- Hover gap between row and flyout (old `margin-left`) would close the menu -> remove the margin.
