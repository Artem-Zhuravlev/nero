# SPEC-110 — Товарные блоки: заголовки в div, ровный отступ

**Status:** implemented
**Owner:** Zhuravlev Artem
**Last updated:** 2026-10-29
**Refines:** SPEC-056 (товарные модули), SPEC-106

## Problem

Заголовок товарного блока («Рекомендуємо») вплотную к карточкам (у `.ocs-modcards` и `.ocs-viewed` нет отступа под заголовком), а заголовки блоков и название товара в карточке обёрнуты в `h2`/`h4`.

## Scope

- Шаблоны модулей: bestseller, featured, featured_product, latest, special, blog_featured, blog_latest, featured_article, ocs_viewed, ocs_latest_reviews, ocs_product_carousel; карточка `ocs/card.twig`.
- `custom/theme/ocs/stylesheet/stylesheet.css`.

## Out of scope

`h1` страниц, модуль HTML, ядро `core/`.

## Functional requirements

- **REQ-PBH-001** — Заголовок блока — `<div class="ocs-sec-title">`, название товара в карточке — `<div class="ocs-card-name">`; тегов `h1`–`h5` в этих шаблонах нет.
- **REQ-PBH-002** — Между заголовком товарного блока и карточками отступ `--sp-4`; стили названия карточки переведены с `h4` на `.ocs-card-name`.

## Acceptance criteria

- [x] `npm run verify` зелёный; архив темы пересобран.
