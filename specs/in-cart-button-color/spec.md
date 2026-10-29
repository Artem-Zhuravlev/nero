# SPEC-114 — Кнопка купленного товара: фон #222

**Status:** implemented
**Owner:** Zhuravlev Artem
**Last updated:** 2026-10-29
**Refines:** SPEC-006 (theme)

## Problem

Кнопка товара, уже лежащего в корзине (`.is-in-cart`), зелёная; нужен тёмный фон `#222`.

## Scope

`custom/theme/ocs/stylesheet/stylesheet.css`.

## Out of scope

Иконка, анимация добавления, остальные кнопки.

## Requirements

- **REQ-CART-001** — Фон `.is-in-cart` (и в `:hover`) равен `#222`.

## Acceptance criteria

- [x] Кнопка купленного товара тёмная `#222`; `npm run verify` зелёный; архив темы пересобран.
