# Spec-Driven Development

Product code is never written from a free-form request alone — every non-trivial change is
defined here first.

```
Requirement -> Spec -> Plan -> Tasks -> Implementation -> Tests -> Verification -> Commit
```

## Hard rule

```
NO SPEC = NO IMPLEMENTATION = NO COMMIT
```

If a request is ambiguous, or asks to "just quickly add" something, stop and write or update the
spec first. If implementation reveals the spec is wrong or incomplete:

```
STOP -> UPDATE SPEC -> RE-APPROVE -> CONTINUE
```

Never silently change requirements through code.

## Layout

```
specs/<feature-name>/
├── spec.md      — problem, scope, requirements, acceptance criteria
├── plan.md      — technical approach, architecture decisions, affected files
└── tasks.md     — ordered implementation tasks, each traceable to a requirement
```

Start new specs from [`_template/`](_template/spec.md).

## Status lifecycle

| Status        | Meaning                                                      |
| ------------- | ------------------------------------------------------------ |
| `draft`       | Being written or discussed. Not implementable.               |
| `approved`    | Reviewed and signed off. Implementation may begin.           |
| `in-progress` | Implementation underway against an approved spec.            |
| `implemented` | Code complete, matches the spec, checks exist and pass.      |
| `verified`    | Implementation re-checked against the spec; considered done. |
| `deprecated`  | No longer applicable; kept for history.                      |

Only `approved` (or later) specs may be implemented. Only `verified` specs are complete.

## Traceability

Every requirement has an ID (`REQ-<AREA>-<NNN>`). Every task in `tasks.md` references the
requirement(s) it fulfills. Every automated check references the requirement it verifies
(`TEST-<AREA>-<NNN> — verifies REQ-<AREA>-<NNN>`, as a comment or in the check's name).

```
REQ-ARCH-001 -> TASK-ARCH-001 -> TEST-ARCH-001
```

## Commits

Commits reference the spec they implement:

```
feat(theme): add base theme skeleton [SPEC-003]
```

## Current specs

| ID       | Feature                                                  | Status  |
| -------- | -------------------------------------------------------- | ------- |
| SPEC-001 | [Project Architecture](project-architecture/spec.md)     | `in-progress` |
| SPEC-003 | [Build Overlay and Generated Configuration](build-overlay/spec.md) | `in-progress` |
| SPEC-004 | [Extension Contracts](extension-contracts/spec.md)       | `approved` |
| SPEC-005 | [Database, Migrations, Settings-as-Code](database-migrations/spec.md) | `in-progress` |
| SPEC-006 | [Storefront Theme Architecture](theme-architecture/spec.md) | `in-progress` |
| SPEC-007 | [OCMOD and Event Policy](ocmod-and-events/spec.md)       | `draft` |
| SPEC-008 | [Localisation](localisation/spec.md)                     | `draft` |
| SPEC-009 | [Quality Gates, Lint, Security Checklist](quality-gates/spec.md) | `in-progress` |
| SPEC-010 | [Admin Access, Users, Permissions, API](admin-access/spec.md) | `draft` |
| SPEC-011 | [Orders and Checkout](orders-checkout/spec.md)           | `draft` |
| SPEC-012 | [Catalogue Data Model](catalog-data-model/spec.md)       | `draft` |
| SPEC-013 | [SEO, Sitemaps and Search](seo-search/spec.md)           | `draft` |
| SPEC-014 | [Operations: backups, logs, jobs, runbooks](operations/spec.md) | `draft` |
| SPEC-015 | [Customer Accounts, Groups, Privacy](customer-accounts/spec.md) | `draft` |
| SPEC-016 | [Content: Pages, Blog, Banners, Layouts](content-blog/spec.md) | `draft` |
| SPEC-017 | [Marketing, Analytics, Consent, Returns](marketing-analytics/spec.md) | `draft` |
| SPEC-018 | [External Integrations Architecture](integrations/spec.md) | `draft` |
| SPEC-019 | [Performance, Scalability, Availability](performance-availability/spec.md) | `draft` |
| SPEC-020 | [Agent Workflow and Knowledge Base](agent-workflow/spec.md) | `draft` |
| SPEC-021 | [Testing Strategy and Harness](testing-strategy/spec.md) | `draft` |
| SPEC-022 | [Multi-store and Environment Profiles](multi-store-environments/spec.md) | `draft` |
| SPEC-023 | [Storefront Visual Design (Apple-style)](theme-design/spec.md) | `in-progress` |
| SPEC-024 | [Storefront Functionality Reference (moyo.ua)](storefront-reference/spec.md) | `in-progress` |
| SPEC-025 | [Quick Order, Pre-order, Back-in-stock](quick-order-preorder/spec.md) | `in-progress` |
| SPEC-026 | [AJAX Cart and Availability](ajax-cart/spec.md) | `in-progress` |
| SPEC-027 | [Mega Menu, Category Wall, Side Menu](menus-category-wall/spec.md) | `in-progress` |
| SPEC-028 | [AJAX Search](ajax-search/spec.md) | `in-progress` |
| SPEC-029 | [Product Stickers](stickers/spec.md) | `in-progress` |
| SPEC-030 | [Recently Viewed Products](viewed-products/spec.md) | `in-progress` |
| SPEC-031 | [Header, Footer, Mobile Shell](header-footer-shell/spec.md) | `in-progress` |
| SPEC-032 | [Cities, Delivery, Stores, Tracking](cities-delivery-stores/spec.md) | `implemented` |
| SPEC-033 | [Home Page Blocks](home-blocks/spec.md) | `implemented` |
| SPEC-034 | [Category Listing](category-listing/spec.md) | `in-progress` |
| SPEC-035 | [Product Page Extras](product-page/spec.md) | `implemented` |
| SPEC-036 | [Instalments and Trade-in](credit-tradein/spec.md) | `superseded` |
| SPEC-037 | [Swiper Carousels and Brief Gaps](swiper-quickview/spec.md) | `implemented` |
| SPEC-038 | [Modern Layout: Grid, Subgrid, Container Queries](modern-layout/spec.md) | `implemented` |
| SPEC-039 | [Visual Identity Pass](visual-identity/spec.md) | `implemented` |
| SPEC-040 | [Installable Theme Package (.ocmod.zip)](demo-bundle/spec.md) | `implemented` |
| SPEC-041 | [Demo Catalogue in the Theme Package](demo-catalogue/spec.md) | `implemented` |
| SPEC-042 | [SVG-Only Icons, Rating Stars, Layer Scale, Bottom Bar](icons-layers-tabbar/spec.md) | `implemented` |
| SPEC-043 | [Mobile Menu, Button Metrics, Full Template Set](menu-buttons-templates/spec.md) | `implemented` |
| SPEC-044 | [Card Actions, Cookie Bookmarks, Quick-View Removal, Stock/SKU Switch](card-actions-bookmarks/spec.md) | `implemented` |
| SPEC-045 | [SEO Heading Markup and Product Page Info Block](seo-markup-pdp/spec.md) | `implemented` |
| SPEC-046 | [Modern Forms and Product Page Sections Instead of Tabs](forms-product-sections/spec.md) | `implemented` |
| SPEC-047 | [Sticky Product Card, Modern Footer, Contacts and Social Settings](footer-sticky-card/spec.md) | `implemented` |
| SPEC-048 | [Product Gallery Lightbox, YouTube Video, Store-wide Info Blocks](gallery-video-infoblocks/spec.md) | `implemented` |
| SPEC-049 | [Product Code, Review/Question Popups, Contact Icons, Inline Language Switch](pdp-popups-contacts-lang/spec.md) | `implemented` |
| SPEC-050 | [Top Menu, Cart Badge, Cart Page, Subcategory Carousel, Sort Dropdown](topmenu-cart-category/spec.md) | `implemented` |
| SPEC-051 | [Rebuild the Theme Package After Every Change](theme-package-rebuild/spec.md) | `draft` |
| SPEC-052 | [Remove the Cities Feature](remove-cities/spec.md) | `implemented` |
| SPEC-053 | [OCMOD as Files in system/](ocmod-as-system-files/spec.md) | `implemented` |
| SPEC-054 | [Breadcrumbs: schema.org, No Self-Link, "Home" Word](breadcrumbs-schema/spec.md) | `implemented` |
| SPEC-055 | [Mega Menu: Full-Height Flyout and Banner Column](megamenu-fullheight-banner/spec.md) | `implemented` |
| SPEC-056 | [Default Product Modules in the Site Card Style](module-product-cards/spec.md) | `implemented` |
| SPEC-057 | [Compare Page: Categories, Differences Only, Table UX](compare-page/spec.md) | `implemented` |
| SPEC-058 | [Header Search Field Style](header-search-style/spec.md) | `implemented` |
| SPEC-059 | [Mobile Header Reduction and Tab Bar Order](mobile-header-tabbar/spec.md) | `implemented` |
| SPEC-060 | [Footer Groups One Column on Mobile](footer-mobile-single-column/spec.md) | `implemented` |
| SPEC-061 | [Mobile Product Grid: Always Two Columns](mobile-two-column-grid/spec.md) | `implemented` |
| SPEC-062 | [Mobile Card Tools Always Visible](mobile-card-tools-visible/spec.md) | `implemented` |
| SPEC-063 | [Customer Account Area in the Site Style](account-area-style/spec.md) | `implemented` |
| SPEC-064 | [Wishlist Page as Product Cards](wishlist-cards/spec.md) | `implemented` |
| SPEC-065 | [Account Menu Visible on Phones](account-menu-mobile/spec.md) | `implemented` |
| SPEC-066 | [Account Menu as a Dropdown on Phones](account-menu-dropdown/spec.md) | `implemented` |
| SPEC-067 | [Style Refresh (Black Accent, Taller Buttons, Gilroy) and Contacts Page](style-refresh-contacts/spec.md) | `implemented` |
| SPEC-068 | [Demo Data Covers Every Custom Field](demo-custom-fields/spec.md) | `implemented` |
| SPEC-069 | [Unified Alerts and No Click Outline](alert-unification/spec.md) | `implemented` |
| SPEC-070 | [Already in Cart Button State](in-cart-button/spec.md) | `implemented` |
| SPEC-071 | [Sidebar Category Item Spacing](sidebar-item-spacing/spec.md) | `implemented` |
| SPEC-072 | [Footer Menu Visibility, Merged Column, Payment Logos](footer-menu-payments/spec.md) | `implemented` |
| SPEC-073 | [Product Gallery Stickers Top Right](product-gallery-stickers/spec.md) | `implemented` |
| SPEC-074 | [Brand Search on Manufacturer List](manufacturer-search/spec.md) | `implemented` |
| SPEC-075 | [Brands Page Visual Redesign](manufacturer-page-style/spec.md) | `implemented` |
| SPEC-076 | [Image Resize Guard (OCMOD) and Migration 0008 Fix](image-resize-guard/spec.md) | `implemented` |
| SPEC-077 | [Out-of-stock Cards: Bell Button, Grey Image, Price Size, Even Spacing](card-out-of-stock-layout/spec.md) | `implemented` |
| SPEC-078 | [Search Page Without the Search Form](search-page-no-form/spec.md) | `implemented` |
| SPEC-079 | [Super Search: language, layout, typo, translit, synonyms](super-search/spec.md) | `implemented` |
| SPEC-080 | [Structured Data (schema.org JSON-LD) for All Page Types](structured-data/spec.md) | `implemented` |
| SPEC-081 | [Latest Reviews Module](latest-reviews/spec.md) | `implemented` |
| SPEC-082 | [Top Bar Above the Header (text, link, button)](topbar/spec.md) | `implemented` |
| SPEC-083 | [Cookie Terms Popup](cookie-popup/spec.md) | `implemented` |
| SPEC-084 | [Blog: Authors, Dates, Multilingual Settings, Comments, Likes](blog-authors/spec.md) | `implemented` |
| SPEC-085 | [Nova Poshta Delivery: Local Directories, City and Branch Suggest in Checkout](novaposhta-delivery/spec.md) | `implemented` |
| SPEC-086 | [Таймер акции в карточке товара](special-countdown/spec.md) | `implemented` |
| SPEC-087 | [Remove Instalments and Trade-in](remove-credit-tradein/spec.md) | `implemented` |
| SPEC-088 | [Логотип в верхней панели, контакты рядом с языком](header-logo-topbar/spec.md) | `implemented` |
| SPEC-089 | [Контакты первым пунктом верхнего меню](topmenu-contacts-first/spec.md) | `implemented` |
| SPEC-090 | [Мобильная шапка в одну строку (sticky) и кастомное мобильное меню](mobile-header-sticky-menu/spec.md) | `implemented` |
| SPEC-091 | [Бейдж «Экономия» в карточке товара](product-savings-badge/spec.md) | `implemented` |
| SPEC-092 | [Аккуратная вёрстка блока подписки](newsletter-block-layout/spec.md) | `implemented` |
| SPEC-093 | [Компактная шапка товара в сравнении](compare-compact-head/spec.md) | `implemented` |
| SPEC-094 | [SEO на страницах пагинации категории и производителя](pagination-seo/spec.md) | `implemented` |
| SPEC-095 | [Панель сортировки в листингах](listing-toolbar/spec.md) | `implemented` |
| SPEC-096 | [Кнопка «Показать ещё» только при наличии пагинации](load-more-visibility/spec.md) | `implemented` |
| SPEC-097 | [Плейсхолдеры в форме запроса, крестик без подложки](modal-close-placeholders/spec.md) | `implemented` |
| SPEC-098 | [Липкий блок товара без производителя, наличия и кода](product-side-card-slim/spec.md) | `implemented` |
| SPEC-099 | [Единый стиль блоков с товарами и статьями](product-blocks-unify/spec.md) | `implemented` |
| SPEC-100 | [Карточка товара: при наведении только тень](card-hover-shadow-only/spec.md) | `implemented` |
| SPEC-101 | [Карточка товара: степпер количества](pdp-qty-stepper/spec.md) | `implemented` |
| SPEC-102 | [Шрифт Montserrat и минимальный размер текста 14px](font-montserrat-min-size/spec.md) | `implemented` |
| SPEC-103 | [Главное меню: без «Показать все», стрелка, отступы](mega-menu-polish/spec.md) | `implemented` |
| SPEC-104 | [Бренды отдельным пунктом главного меню](mega-menu-brands/spec.md) | `implemented` |
| SPEC-105 | [Стрелка у каждого пункта мега-меню с дочерними](mega-menu-arrows/spec.md) | `implemented` |
| SPEC-106 | [Мобильные товарные модули каруселью, 2 в кадре](mobile-product-carousels/spec.md) | `implemented` |
| SPEC-107 | [Моб. меню: логотип в шапке панели, инфо-страницы под details](mobile-menu-logo-pages/spec.md) | `implemented` |
| SPEC-108 | [Футер: названия групп в summary](footer-group-titles/spec.md) | `implemented` |
| SPEC-109 | [Слайдер: без серой полосы, скруглённые края фото](hero-slider-radius/spec.md) | `implemented` |
| SPEC-110 | [Товарные блоки: заголовки в div, ровный отступ](product-block-headings/spec.md) | `implemented` |
| SPEC-111 | [Карточка товара на телефоне: sticky купить над нижней навигацией](pdp-mobile-sticky/spec.md) | `implemented` |
| SPEC-112 | [Футер: адрес под графиком работы](footer-address-order/spec.md) | `implemented` |
| SPEC-113 | [Товар не в наличии: без «Купити», меньшая цена](product-out-of-stock-buy/spec.md) | `implemented` |

SPEC-002 is intentionally unused (a deployment spec was dropped; deployment will get its own
number when requested).
