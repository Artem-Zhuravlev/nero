# PLAN — SPEC-071 Sidebar Category Item Spacing

## Approach

Make `.list-group` a flex column with `gap: var(--sp-1)` in the SPEC-039 block of `stylesheet.css`.

## Affected files

- `custom/theme/ocs/stylesheet/stylesheet.css`, `dist/ocs-theme-<VERSION>.ocmod.zip`, `specs/README.md`

## Risks

- Other `.list-group` blocks (filter, blog categories) also get the gap; intended.
