# SPEC-109 — Слайдер: без серой полосы, скруглённые края фото

**Status:** implemented
**Owner:** Zhuravlev Artem
**Last updated:** 2026-10-28
**Refines:** SPEC-033 (главная: hero-слайдер)

## Problem

Под фото слайдера виден серый фон `--bg-alt` со скруглением, а нижние углы самого фото прямые: inline-элемент `<picture>` оставляет строчный зазор под `<img>`, сквозь который просвечивает фон слайда.

## Scope

- `custom/theme/ocs/stylesheet/stylesheet.css`: блок hero.

## Out of scope

Шаблон `ocs_home_slider.twig`, ядро `core/`.

## Functional requirements

- **REQ-HRR-001** — `<picture>` в слайде hero — блок (нет зазора под фото), у слайда нет фона, фото имеет `border-radius: var(--r-lg)` со всех сторон.

## Acceptance criteria

- [x] Под фото слайдера белый фон, все углы фото скруглены.
- [x] `npm run verify` зелёный; архив темы пересобран.
