# SPEC-093 — Компактная шапка товара в сравнении

**Status:** implemented
**Owner:** Zhuravlev Artem
**Last updated:** 2026-10-28
**Refines:** SPEC-057 (compare page)

## Problem

Блок товара в шапке таблицы сравнения высокий: крупное фото, цена и широкая кнопка «Купить» друг под другом.

## Scope

`custom/theme/ocs/template/product/compare.twig` и стили `.ocs-cmp-*`; логика не меняется.

## Requirements

- **REQ-CMPH-001** — Фото уменьшено (4.5rem), цена (и наличие) стоит рядом с фото справа от него.
- **REQ-CMPH-002** — Кнопка «Купить» — круглая `.ocs-cart-btn` с иконкой корзины, как в карточке категории; в одной строке с рейтингом, `aria-label`/`title` сохранены.

## Acceptance criteria

- [x] Шапка колонки заметно ниже; `npm run verify` зелёный; архив темы пересобран.
