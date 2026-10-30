# SPEC-102 — Шрифт Montserrat и минимальный размер текста 14px

**Status:** implemented
**Owner:** Zhuravlev Artem
**Last updated:** 2026-10-28
**Refines:** SPEC-006 (тема), visual-identity (шрифт Gilroy)

## Problem

В теме `ocs` много мелкого текста: токен `--fs-xs` равен 12px (41 использование), плюс счётчики 10–11px. У интерактивных элементов (кнопки, ссылки, табы, чипы, поля) это плохо читается. Шрифт Gilroy коммерческий и в репозитории отсутствует (сайт показывает системный fallback).

## Scope

CSS и шапка темы `ocs`: шкала размеров `--fs-*`, прямые значения `font-size`, `@font-face`/`--font`, preload в `header.twig`, `fonts/`, `DESIGN.md`, тест `theme.test.mjs`.

## Out of scope

Админка, шрифты внутри картинок/SVG (логотипы платёжных систем в футере), email-шаблоны.

## Requirements

- **REQ-FNT-001** — Основной шрифт сайта — Montserrat (веса 400/500/600/700/800, latin + cyrillic), self-host из `fonts/` (OFL, без запросов к сторонним хостам), `font-display: swap`, preload двух основных весов, системный fallback.
- **REQ-FNT-002** — Ни один интерактивный элемент (a, button, input/select/textarea, табы, чипы, пункты меню, подписи полей) не имеет размера меньше 14px.
- **REQ-FNT-003** — Минимальный размер любого текста — 14px (`--fs-xs: 14px`). Единственное исключение — чисто декоративные элементы (цифра-бейдж на иконках корзины/избранного/сравнения), там не менее 12px; список исключений фиксируется в `DESIGN.md`. Исключение по решению владельца: хлебные крошки, стикеры (`.ocs-badge`) и строка отзывов на карточке товара — 13px.
- **REQ-FNT-004** — Шкала увеличена: `--fs-xs` 14, `--fs-sm` 15, `--fs-md` 16, `--fs-lg` 20, `--fs-xl` 26, `--fs-2xl` 34, `--fs-3xl` 48 (Montserrat шире Gilroy, поэтому крупные ступени растут умеренно).
- **REQ-FNT-005** — Вёрстка не ломается: нет горизонтального скролла и обрезанных подписей на 375px и 1280px (проверка в браузере по ключевым страницам).

## Acceptance criteria

- [x] В `stylesheet.css` нет `font-size` < 14px, кроме перечисленных декоративных исключений; нет упоминаний Gilroy (кроме ручной проверки вёрстки TEST-FNT-003, она не выполнена).
- [x] Файлы `montserrat-*.woff2` лежат в `custom/theme/ocs/fonts/`, лицензия OFL указана в `fonts/README.txt`.
- [x] `npm run verify` зелёный; архив темы пересобран (`npm run pack:theme`).

## Tests

- **TEST-FNT-001 — verifies REQ-FNT-001** — `theme.test.mjs`: @font-face Montserrat, swap, preload, `--font`.
- **TEST-FNT-002 — verifies REQ-FNT-002, REQ-FNT-003, REQ-FNT-004** — `theme.test.mjs`: все `font-size` в CSS ≥ 14px (кроме белого списка декоративных), значения шкалы.
- **TEST-FNT-003 — verifies REQ-FNT-005** — ручная проверка в браузере (375/1280): главная, категория, товар, корзина.
