# SPEC-112 — Футер: адрес под графиком работы

**Status:** implemented
**Owner:** Zhuravlev Artem
**Last updated:** 2026-10-29
**Refines:** SPEC-047 (контакты в футере)

## Problem

В блоке «Контакти» футера адрес стоит первым, перед телефонами. Нужно, чтобы он шёл после графика работы.

## Scope

- `theme/ocs/template/common/footer.twig`: строка адреса переносится под строку графика работы.

## Out of scope

CSS, ядро `core/`, шапка и страница контактов.

## Functional requirements

- **REQ-FAO-001** — в `.ocs-foot-contacts` строка `<address>` выводится после строки графика работы (`ocs_shell.hours`) и до иконок мессенджеров/соцсетей.

## Acceptance criteria

- [x] Порядок в футере: телефоны, график, адрес.
- [x] `npm run verify` зелёный; архив темы пересобран.
