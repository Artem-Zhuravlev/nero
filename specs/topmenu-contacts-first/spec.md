# SPEC-089 — Контакты первым пунктом верхнего меню

**Status:** implemented
**Owner:** Zhuravlev Artem
**Last updated:** 2026-10-28

## Problem

Страница `information/contact` лежит внутри выпадающего списка «Інформація» (последним пунктом) и там не заметна.

## Purpose

Вынести «Контакти» из выпадающего списка в верхнее меню: Контакти · Акції · Блог · «Інформація ⌄».

## Scope

- `custom/extensions/ocs/upload/system/library/ocs/topmenu.php`: `view()` отдаёт `contacts` отдельным элементом, а не последней строкой `pages`.
- `custom/theme/ocs/template/common/header.twig`: порядок контакты → акции → блог → выпадающий список.
- Тесты `TopMenuTest.php`, `topmenu-cart.test.mjs`; пакет темы.

## Out of scope

- Настройки (флаг `theme_ocs_top_menu[contacts]` остаётся), информационные страницы, `core/`, мобильное меню.

## Functional requirements

- **REQ-TOP-030** — Если включён флаг `contacts` и ссылка есть, «Контакти» выводится отдельной ссылкой `.ocs-top-link` первой в `.ocs-top`, до «Акції»; в выпадающем списке её нет.
- **REQ-TOP-031** — Порядок в `.ocs-top`: Контакти · Акції · Блог · выпадающий список «Інформація».
- **REQ-TOP-032** — Активная страница контактов помечается `is-active` / `aria-current="page"`.
- **REQ-TOP-033** — Меню показывается, если есть хотя бы один из: страницы, контакты, блог, акции; выпадающий список не выводится при пустых `pages`.

## Non-functional requirements

- **REQ-TOP-034** — Ничего не правится в `core/`; вывод экранирован как раньше.

## Data requirements

Без изменений данных.

## Edge cases

- Блог выключен (`ocs_top.blog` пуст) — `{{ blog_menu }}` остаётся как раньше.
- Выбраны только контакты — остаётся одна ссылка, без выпадающего списка.
