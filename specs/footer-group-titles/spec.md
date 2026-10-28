# SPEC-108 — Футер: названия групп в summary

**Status:** implemented
**Owner:** Zhuravlev Artem
**Last updated:** 2026-10-28
**Refines:** SPEC-072 (ссылки футера по умолчанию), SPEC-047 (сворачиваемые группы футера)

## Problem

В футере группы по умолчанию («Інформація», «Служба підтримки», «Додатково») выводятся с пустым `<summary>`: видна только стрелка. События `applyDisplay` (порядок 99) выполняется раньше языкового события ядра `event/language` (порядок 999), поэтому `text_information`, `text_service`, `text_extra` ещё не лежат в данных вида, и `Ocs\Footer::view` получает пустые заголовки.

## Scope

- `extensions/ocs/.../events.php`, метод `footer()`: перед `Footer::view` дополняет данные строками загруженного языка.

## Out of scope

Шаблон `footer.twig`, CSS, ядро `core/`.

## Functional requirements

- **REQ-FGT-001** — `footer()` передаёт в `Ocs\Footer::view` данные вида, дополненные строками `$this->language->all()` (без перезаписи существующих ключей), чтобы заголовки групп не были пустыми.

## Acceptance criteria

- [x] В `<summary>` каждой группы футера виден заголовок.
- [x] `npm run verify` зелёный; архив темы пересобран.
