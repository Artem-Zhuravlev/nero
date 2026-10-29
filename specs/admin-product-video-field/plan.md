# PLAN — SPEC-115 Admin product form: video field on Data tab

## Approach

Rework the existing admin event: stop building the tab link/pane, render only the video form-group into `ocs_video_field`; change the OCMOD to insert it after the model form-group. Drop accessory code from the admin event, model (`getAccessoryIds`, `setAccessories`, `getProductNames`) and tab language strings. Rebuild the theme archive.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Rendering | Event renders HTML, OCMOD prints variable | Same pattern as before; core template stays untouched |
| Template | Keep `tab.twig`, reduce to the video group + marker | Minimal diff |

## Affected files / directories

- `custom/ocmod/ocs_product_extras_form.ocmod.xml`
- `custom/extensions/ocs_product_extras/upload/admin/{controller,model,view,language}/…`
- `scripts/test/php/ProductExtrasTest.php` (if it covers the admin code)
- `dist/ocs-theme-<VERSION>.ocmod.zip` (npm run pack:theme)

## Risks

- OCMOD search string of the model group must match core exactly -> anchor on the unique `{{ error_model }}` block, verify with `refresh`/verify.
