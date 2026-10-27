# PLAN — SPEC-065

In the phone `@container page (max-width: 56rem)` block of the SPEC-063 account section in `stylesheet.css` add
`#column-right.hidden-xs:has(.ocs-acc-nav), #column-left.hidden-xs:has(.ocs-acc-nav) { display: block !important; }`.
`!important` is needed to beat Bootstrap's `!important`; layered important declarations win over unlayered ones. CSS only.
