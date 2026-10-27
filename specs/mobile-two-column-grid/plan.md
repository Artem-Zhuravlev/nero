# PLAN — SPEC-061

In the existing `@container page (max-width: 40rem)` block of `custom/theme/ocs/stylesheet/stylesheet.css` set
`grid-template-columns: repeat(2, minmax(0, 1fr))` for `#ocs-grid, .ocs-viewed-grid, .ocs-cards`. CSS only.
Side-column rule `#column-left .ocs-cards` has higher specificity and stays single-column.
