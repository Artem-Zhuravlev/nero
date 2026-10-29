# PLAN — SPEC-119

Контроллер `ocs_ajax_cart::state()` считает `old_price`/`old_total` по тому же правилу, что `cartOldPrices` (цена товара + надбавка опций > текущая цена). Шаблон выводит `<s class="ocs-old">`. Стиль `.ocs-old` уже есть.
