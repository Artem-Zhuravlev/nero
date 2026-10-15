# PLAN — SPEC-006 Theme Architecture

## Approach

Start from `default`, override only what a design spec needs. Header override first (assets,
version query, no CDN), then home/menu/product/category. Admin theme extension is a copy of
`admin/controller/extension/theme/default.php` renamed `ocs`, saving `theme_ocs` settings.

## Decisions

| Decision | Choice | Why |
| -------- | ------ | --- |
| CSS pipeline | committed CSS, optional SCSS | no Node needed on servers; core SCSS compile is dev-only |
| Fonts | self-hosted | privacy/perf; core default uses Google CDN |
| Cache busting | `asset_version` via view event | avoids patching header via OCMOD |
| Theme Editor | restricted | DB overrides bypass git |

## Affected files

`custom/theme/ocs/**`, `custom/extensions/ocs/**` (theme extension), migration `0002_theme_ocs.php`,
event handler `custom/extensions/ocs_theme_support/…/events.php` (library-type extension that
holds shared theme events).

## Risks

- `common.js` couples to DOM -> HOOKS.md review.
- Core `event/theme` consults `DIR_MODIFICATION` first for patched templates (OCMOD pitfall).
