# PLAN — SPEC-122 Редактор заказа на одной странице

## Approach

Новое расширение `ocs_order_editor` в `custom/extensions/`. Admin event `admin/view/sale/order_form/before` подменяет `$route` на собственный Twig-шаблон; контроллер ядра `sale/order` по-прежнему готовит все переменные (магазины, валюты, группы, страны, custom fields, данные заказа, `api_token`, `catalog`). Шаблон — одна страница на Bootstrap 3 с блоками; JS (`admin/view/javascript/ocs/order_editor.js`) реализует очередь синхронизации с API каталога по тому же протоколу, что и ядро, но автоматически:

`customer -> cart (товары) -> payment/address -> shipping/address -> shipping/methods -> payment/methods -> shipping/method -> payment/method -> coupon/voucher/reward -> cart/products (итоги) -> order/add|edit`.

Любое изменение поля ставит в очередь пересчёт нужной части цепочки с debounce; ответы с устаревшим номером запроса отбрасываются. Если «адрес плательщика совпадает» — в `api/payment/address` уходит копия полей доставки.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Подмена формы | admin view event (смена route) | OCMOD на 2500-строчный twig хрупок; core read-only |
| Бэкенд | не менять, использовать API каталога | Вся бизнес-логика заказа уже там, меньше багов |
| Адрес | один блок + флажок «плательщик совпадает» | Требование пользователя |
| Синхронизация | автоматическая очередь с debounce | Убирает кнопки «Продолжить» |
| UI | Bootstrap 3 админки + свой CSS | Консистентность с админкой |

## Affected files / directories

- `custom/extensions/ocs_order_editor/extension.json`, `db/001_install.php` (регистрация события)
- `.../upload/admin/controller/extension/ocs_order_editor/events.php`
- `.../upload/admin/view/template/extension/ocs_order_editor/order_form.twig`
- `.../upload/admin/view/javascript/ocs_order_editor/order_editor.js`, `.../stylesheet/ocs_order_editor/order_editor.css`
- `.../upload/admin/language/{uk-ua,en-gb}/extension/ocs_order_editor/editor.php`
- `scripts/test/` — тесты; пересобрать `dist/ocs-theme-<VERSION>.ocmod.zip`

## Risks

- Тонкости протокола API (порядок вызовов, сброс shipping при смене адреса) -> повторить последовательность ядра. В репозитории нет запущенного MySQL, поэтому проверка статическая + ваш прогон на сайте.
- Расширения доставки/оплаты со своими полями (Новая Почта) -> способ отдаётся как есть, поля сторонних модулей проверить вручную.
- Размер задачи -> делить на блоки (клиент/товары/адрес/итоги), каждый проверяемый.

## Implementation notes

- Custom fields are sent both flat (`custom_field[id]`, what the order stores) and nested (`custom_field[location][id]`, what the catalog API validates); the stock form sent only the flat shape so required custom fields always failed.
- Fields of type `file` (customer/address custom fields, product options) are not supported in the editor; a product with a required file option cannot be added here.
- Verification: static tests plus a browser run against a mock catalog API (new order, edit, validation errors, API down); the real API, Nova Poshta quotes and payment modules need a manual pass on the site.
