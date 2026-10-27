# PLAN — SPEC-066

`theme.js` (account area IIFE): create `button.ocs-acc-toggle` before `.ocs-acc-nav`, set `data-collapsed` on the nav, toggle on click.
CSS: toggle hidden by default; in the phone container query it is shown, `.ocs-acc-nav[data-collapsed]` is hidden, the strip styles are dropped.
