# SPEC-119 — Попап корзины: акционная и актуальная цена

**Status:** implemented
**Owner:** Zhuravlev Artem
**Last updated:** 2026-10-29
**Refines:** SPEC-026 (ajax cart), SPEC-117

## Problem

На странице корзины для акционного товара показываются старая (перечёркнутая) и актуальная цена, а в боковом попапе корзины — только актуальная сумма.

## Scope

`custom/extensions/ocs_ajax_cart/upload/catalog/controller/extension/module/ocs_ajax_cart.php`, шаблон `ocs_ajax_cart.twig`, `custom/theme/ocs/stylesheet/stylesheet.css`.

## Out of scope

Итоги корзины, серверный расчёт цен ядра, страница корзины.

## Requirements

- **REQ-CART-030** — В строке попапа, если регулярная цена (с опциями) выше актуальной, выводится перечёркнутая старая сумма строки рядом с актуальной; правило расчёта то же, что на странице корзины (`cartOldPrices`).
- **REQ-CART-031** — Без скидки вид строки не меняется; цены только для отображения, итоги остаются из ядра.
- **REQ-CART-032** — Ядро не тронуто; архив темы пересобран.

## Acceptance criteria

- [x] Акционный товар в попапе: старая сумма перечёркнута, рядом актуальная.
- [x] `npm run verify` зелёный; `npm run pack:theme` выполнен.
