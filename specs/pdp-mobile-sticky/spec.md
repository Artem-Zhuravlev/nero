# SPEC-111 — Карточка товара на телефоне: без sticky-табов, купить над нижней навигацией

**Status:** implemented
**Owner:** Zhuravlev Artem
**Last updated:** 2026-10-29
**Refines:** SPEC-035 (панель разделов), SPEC-039 (sticky-блок покупки)

## Problem

На телефоне панель разделов («Про товар, Відгуки, Питання, Схожі товари») липнет к шапке, а sticky-блок с фото, ценой и кнопкой «Купити» висит под ней вверху.

## Scope

`custom/theme/ocs/stylesheet/stylesheet.css` (только ширина < 768px).

## Functional requirements

- **REQ-PMS-001** — На ширине < 768px панель разделов `.ocs-sections` не sticky (скроллится вместе со страницей).
- **REQ-PMS-002** — На ширине < 768px `.ocs-sticky-buy` закреплён внизу, непосредственно над нижней навигацией `.ocs-tabbar`; анимация появления — снизу.

## Acceptance criteria

- [x] `npm run verify` зелёный; архив темы пересобран.
