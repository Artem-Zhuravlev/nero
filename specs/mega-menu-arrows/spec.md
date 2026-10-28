# SPEC-105 — Мега-меню: стрелка у каждого пункта с дочерними

**Status:** implemented
**Owner:** Zhuravlev Artem
**Last updated:** 2026-10-28
**Refines:** SPEC-027 (мега-меню), SPEC-103

## Problem

На десктопе `.ocs-mega-item` имеет `position: static` (чтобы flyout позиционировался от панели), поэтому абсолютно позиционированные кнопки `.ocs-mega-expand` все привязываются к верху панели и накладываются друг на друга: стрелка видна только у первого пункта.

## Scope

CSS мега-меню в теме `ocs` (десктоп, `min-width: 768px`).

## Out of scope

Мобильная раскладка, разметка шаблона.

## Requirements

- **REQ-MMA-001** — На десктопе стрелка `.ocs-mega-expand` отображается в строке каждого пункта с дочерними, а не накладывается на первый; flyout по-прежнему позиционируется от панели.

## Acceptance criteria

- [x] `npm run verify` зелёный; архив темы пересобран.

- **TEST-MMA-001 — verifies REQ-MMA-001** — `scripts/test/mega-menu-arrows.test.mjs`.
