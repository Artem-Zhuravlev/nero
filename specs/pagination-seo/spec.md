# SPEC-094 — SEO на страницах пагинации категории и производителя

**Status:** implemented
**Owner:** Zhuravlev Artem
**Last updated:** 2026-10-28
**Refines:** SPEC-034 (listing), SPEC-013 (SEO)

## Problem

На страницах `?page=2…` категории и производителя повторяются SEO-текст (описание) и одинаковые `title`/`description`, что даёт дубли контента и мета-тегов.

## Scope

Страницы `product/category` и `product/manufacturer/info` при `page` > 1.

## Out of scope

Страница 1 (без `page` или `page=1`), поиск, акции, canonical/prev/next (уже есть в ядре).

## Requirements

- **REQ-PGSEO-001** — При `page` > 1 описание (SEO-текст) категории и производителя не выводится.
- **REQ-PGSEO-002** — При `page` > 1 к `<title>` и `<meta name="description">` в конец добавляется номер страницы, например «Название - страница 2».
- **REQ-PGSEO-003** — Текст суффикса мультиязычный (`ocs_page_suffix` в языковых файлах ru-ru, uk-ua, en-gb), берётся из текущего языка покупателя.
- **REQ-PGSEO-004** — Реализация через события (core не правится); любая ошибка не ломает страницу.

## Interfaces

События: существующие `catalog/view/product/{category,manufacturer/info}/before` (`ocs_listing`), новое `catalog/view/common/header/before` (миграция `003`).

## Acceptance criteria

- [x] `?page=2`: нет блока описания, `title`/`description` с номером страницы; страница 1 без изменений.
- [x] Строки есть во всех трёх языках; `npm run verify` зелёный; архив темы пересобран.
