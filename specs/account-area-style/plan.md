# PLAN — SPEC-063

1. Add an account section (`components` layer) to `stylesheet.css`: `.ocs-acc-grid`, `.ocs-acc-card`, `.ocs-acc-tile`, sidebar `.ocs-acc-nav`, responsive tables (`.ocs-rtable` with `data-label`), badges, empty state, narrow form width. Container queries on `page`.
2. Rewrite `account/account.twig` and `extension/module/account.twig` (active item via `route` comparison; if the module controller doesn't expose the current route, derive it in Twig from the item's href, otherwise add a tiny OCMOD-free event only if unavoidable — decided in implementation, spec updated if so).
3. Update list templates (`order_list`, `download`, `reward`, `transaction`, `return_list`, `recurring_list`, `address_list`, `wishlist`) for REQ-ACC-007..010; forms for REQ-ACC-011.
4. Check at 375 / 1280 px in the browser pane; `npm run verify`; `npm run pack:theme`.
