# SPEC-100 — Карточка товара: при наведении только тень

**Status:** implemented
**Owner:** Zhuravlev Artem
**Last updated:** 2026-10-28
**Refines:** SPEC-039 (карточка товара)

## Problem

При наведении на карточку товара изображение увеличивается (`scale: 1.04`) и вся карточка приподнимается на 2px. Нужна только тень.

## Scope

CSS карточки `.product-thumb` в теме `ocs`.

## Out of scope

Инструменты поверх карточки (избранное, сравнение), тень и её параметры.

## Requirements

- **REQ-CHS-001** — При наведении/фокусе на карточку изображение не масштабируется.
- **REQ-CHS-002** — Карточка не смещается; меняется только `box-shadow`.

## Acceptance criteria

- [x] В `stylesheet.css` нет `scale` для `.ocs-card-img img` и `translate` для `.product-thumb:hover`; `npm run verify` зелёный; архив темы пересобран.

- **TEST-CHS-001 — verifies REQ-CHS-001, REQ-CHS-002** — `scripts/test/card-hover-shadow.test.mjs`.
