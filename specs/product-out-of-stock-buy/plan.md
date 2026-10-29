# PLAN — SPEC-113

1. `product.twig`: `#button-cart` в `{% if not ocs_preorder %}`; убрать вывод `ocs_preorder.note`.
2. `stylesheet.css`: `.ocs-preorder #button-cart { display: none !important; }`; `.ocs-price` -> `--fs-2xl`.
3. TEST-OOS-001..003, `npm run verify`, `npm run pack:theme`, строка в `specs/README.md`.
