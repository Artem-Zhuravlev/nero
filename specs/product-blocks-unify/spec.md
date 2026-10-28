# SPEC-099 — Единый стиль всех блоков с товарами и статьями

**Status:** implemented
**Owner:** Zhuravlev Artem
**Last updated:** 2026-10-28
**Refines:** SPEC-056 (модули товаров), SPEC-084 (карточки статей), SPEC-006 (theme)

## Problem

Аудит шаблонов показал, что часть блоков всё ещё печатает старую карточку OpenCart (`.product-thumb` + `.button-group`, Bootstrap `col-*`) и выглядит чужой на фоне каталога:

1. `product/product.twig` — блок «Рекомендовані товари» (`products`, `text_related`): ручная старая карточка с описанием и кнопками.
2. `extension/module/featured_article.twig` — модуль «статьи»: старая карточка с `button-group` вместо общей карточки статьи.
3. `blog/article.twig` — «Связанные товары»: используется `ocs/card.twig`, но внутри `div.row` (карточка — не колонка), поэтому сетка отличается от остальных блоков.

Уже в общем стиле (не трогаем): `featured`, `special`, `latest`, `bestseller`, `featured_product`, `ocs_product_carousel`, `ocs_viewed`, аксессуары/похожие на странице товара, `blog_latest`, `blog_featured`, `blog/latest`, `blog/category`, листинги, вишлист.

## Scope

- `product.twig`: related-блок рендерит `ocs/card.twig` в сетке `.ocs-cards`, заголовок `h2.ocs-sec-title` внутри `<section class="ocs-modcards">`.
- Данные карточки related-товаров (`model`, `reviews`, `discount_pct`, `stickers`, `ocs_stock`) добавляются теми же расширениями событием `catalog/view/product/product/before` (миграция в `ocs_listing`, `ocs_stickers`, `ocs_ajax_cart`), как в SPEC-056.
- `featured_article.twig`: `ocs/blog_card.twig` в `.ocs-bgrid`.
- `blog/article.twig`: related-товары в `.ocs-cards`, без `.row`.
- CSS: удалить правила, ставшие мёртвыми (если на `.product-thumb .button-group` больше ничего не ссылается).

## Out of scope

Корзина, сравнение, оформление заказа, заказы в кабинете (это таблицы, не витрины); админка; ядро `core/`.

## Functional requirements

- **REQ-PBU-001** — Related-товары на странице товара выводятся `ocs/card.twig` в `.ocs-cards`; старая разметка (`.button-group`, `col-*`, описание) удалена; пустой список не печатает ничего.
- **REQ-PBU-002** — Карточки related-товаров получают те же ключи данных, что карточки листинга; выключенное расширение просто не добавляет ключи; обработчик не бросает исключений.
- **REQ-PBU-003** — `featured_article.twig` печатает статьи через `ocs/blog_card.twig` в `.ocs-bgrid`, как `blog_latest`.
- **REQ-PBU-004** — Связанные товары в статье блога лежат в `.ocs-cards`.
- **REQ-PBU-005** — Ни один шаблон витрины в `custom/` не содержит старой разметки `.product-thumb .button-group` вне `ocs/card.twig` (проверка: grep в TEST-PBU-001).

## Non-functional requirements

- **REQ-PBU-010** — Не больше одного батч-запроса на расширение; нет горизонтального скролла на 320px.

## Data requirements

Новые строки событий (идемпотентные миграции `ocs_listing`, `ocs_stickers`, `ocs_ajax_cart`); схема и настройки не меняются.

## Edge cases

- Расширение карточек выключено -> карточка рендерится без бейджей/наличия.
- Связанных товаров нет -> блок не выводится.

## Acceptance criteria

- [x] На странице товара «Рекомендовані товари» выглядит как карточки каталога.
- [x] Модуль статей и связанные товары статьи в общем стиле.
- [x] `npm run verify` зелёный; архив темы пересобран.
