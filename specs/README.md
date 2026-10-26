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
| SPEC-036 | [Instalments and Trade-in](credit-tradein/spec.md) | `implemented` |
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

SPEC-002 is intentionally unused (a deployment spec was dropped; deployment will get its own
number when requested).
