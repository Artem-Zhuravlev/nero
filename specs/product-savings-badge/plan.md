# PLAN — SPEC-091 Бейдж «Экономия»

## Approach

В блоке `product/product` события `ocs` (рядом с `ocs_special_timer`) при наличии `special` берём
сырые цены товара из модели `catalog/product`, считаем разницу с налогом (`tax->calculate`) и
форматируем `currency->format(..., session currency)`. В `product.twig` бейдж стоит в одной строке с новой ценой.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Расчёт | сервер, из сырых цен | `$data['price']` уже строка — парсить её нельзя |
| Место | событие `ocs` | без правки core, как SPEC-086 |

## Affected files / directories

- `custom/extensions/ocs/upload/catalog/controller/extension/ocs/events.php`
- `custom/theme/ocs/template/product/product.twig`, `custom/theme/ocs/stylesheet/stylesheet.css`
- языковые файлы `extension/ocs/top.php` (uk-ua, ru-ru, en-gb)
- `custom/VERSION`, `dist/` (pack:theme), строка в `specs/README.md`

## Risks

- Лишний `getProduct` — одна выборка; при необходимости использовать уже загруженные данные контроллера.
