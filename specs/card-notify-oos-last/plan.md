# PLAN — SPEC-120

1. **Why the bell is missing:** `labelNotify()` sets `button.hidden = !body.hasAttribute('data-ocs-notify')`; the attribute comes from
   `ocs_back_in_stock` `beforeHeader`, which returns early while `module_ocs_back_in_stock_status` is 0 (install default). Fix at the root:
   enable the module by a migration (REQ-OOS2-003), ; `card.twig` / `theme.js` stay as in SPEC-077 (bell hidden only when the module is off).
2. Card/JS: no template change needed; a Node test pins the bell wiring.
3. Migration `custom/db/migrations/NNNN_back_in_stock_enabled.php` (next free number, idempotent) + change default in
   `ocs_back_in_stock/db/001_install.php`.
4. OCMOD `ocs_listing_stock_last.ocmod.xml`: in `getProducts()` prefix each ORDER BY variant (`LCASE(sort)`, price CASE, `sort`, default
   `p.sort_order`) with `(p.quantity <= 0) ASC,`; same for `getProductSpecials()`. Operations use `index` to hit the right occurrence
   (verified by grepping `core/upload/catalog/model/catalog/product.php` lines 175-182, 227-232). `p.quantity` is already selected via `p.*`.
5. Tests: Node test that the OCMOD search strings exist exactly once/at the expected index in the core file; PHP test for the migration.
6. `npm run verify`, `npm test`, `npm run pack:theme`, bump `custom/VERSION`, add README row, commit `[SPEC-120]`.
