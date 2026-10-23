# PLAN — SPEC-027 Menus and Category Wall

## Approach

A shared library `Ocs\CategoryTree` builds and caches the tree per language; three modules render it. Mega menu replaces
`{{ menu }}` through a layout position / theme `common/menu` override (SPEC-023 slot). Per-category extras are stored in
an own table, edited in an admin tab added by event.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Extras storage | Own table | No core table change |
| Mobile | Separate drill-down markup | Fly-out does not work on touch |

## Affected files / directories

- `custom/extensions/ocs_mega_menu|ocs_category_wall|ocs_category_menu/`, `system/library/ocs/categorytree.php`,
  theme `common/menu.twig`.

## Risks

- Large trees -> level limit, cache, lazy second level on mobile.
