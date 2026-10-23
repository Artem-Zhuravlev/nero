# SPEC-024 — Storefront Functionality Reference (moyo.ua)

**Status:** in-progress
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-23
**Refines:** SPEC-023 (visual design), client brief `Список модулей.docx` · **Source:** observation of https://www.moyo.ua on 2026-10-23 (home, category "Ноутбуки", product page, footer; narrow browser pane, so some desktop-only blocks may be missing)

## Problem

The client wants the `ocs` template to offer the functional level of a large Ukrainian electronics retailer
(moyo.ua), not only the ten modules of the brief. Without a written inventory, features get built ad hoc, overlap
with each other, or are missed. This spec is a **catalogue and decision list**, not an implementation spec: it records
what the reference does, maps it to the brief's modules, and marks what is new so the owner decides what enters scope.

## Purpose

Inventory the reference storefront's features, split them into (A) already covered by the module list of the brief and
(B) not in the list, assign each to an owning follow-up spec, and fix the rules (configurability, i18n, bought vs built)
that all of them share.

## Scope

A requirement catalogue for the storefront: header, navigation, home, category listing, product card, product page,
footer, mobile shell. Each requirement is tagged `[LIST]` (covered by a numbered module of the brief) or `[NEW]` (not in
the brief), has a priority (`P1` base of the template, `P2` expected by a typical client, `P3` optional/niche) and an
owning follow-up spec.

## Out of scope

Implementation of any feature here (each owner spec does it after approval). Copying moyo.ua text, images, logos or
layout pixel-for-pixel: only the functional idea is borrowed. Features of a single large retailer that make no sense as a
reusable template (own mobile app, 60-store network, own loyalty programme backend, trade-in valuation engine) are
listed under P3 or excluded explicitly in REQ-MOY-090.

## Owner decision (2026-10-23)

The owner asked for functionality close to the reference. Accepted: **all P1 and P2** requirements of section B and all of
section A. **P3** requirements (REQ-MOY-025, 057, 062) are deferred: owner specs may include them as optional blocks but
they are not required. Exclusions of REQ-MOY-090 stand. Owner specs SPEC-025..036 are created as `draft` and approved one by one.

## Cross-cutting rules (all features)

- **REQ-MOY-001** — Every feature is a module or theme part that can be switched off by one setting; with it off the
  storefront renders exactly as without it (SPEC-004 REQ-EXT-080). The template is reused for different niches, so
  nothing electronics-specific is hard-coded (e.g. "Trade-in", "Credit" are optional blocks).
- **REQ-MOY-002** — All user-visible strings in uk-ua, ru-ru, en-gb (brief; SPEC-008). Ukrainian is the default language.
- **REQ-MOY-003** — Buy, do not build, where the brief says so: faceted filter = OCFilter, checkout = Simple. Features
  here only add the styling hooks (SPEC-023 REQ-DSG-026) and do not duplicate them.
- **REQ-MOY-004** — Popups use `OCS.modal` only (SPEC-023 REQ-DSG-013); colours only from tokens (REQ-DSG-001).
- **REQ-MOY-005** — PHP 7.4 compatible (brief; ocStore does not run on newer PHP).

## A. Covered by the brief's module list (`[LIST]`)

| Reference behaviour | Brief module | Priority | Owner spec |
| --- | --- | --- | --- |
| Left vertical catalogue of top categories with brand sub-groups, multi-level fly-out, promo banner tiles | 5. Mega Menu | P1 | SPEC-027 |
| "Best in categories" tile grid on home (category name + link, popular ones highlighted) | 6. Category wall | P1 | SPEC-027 |
| Search with suggestions: popular queries, recent queries with clear, popular categories/products, empty-result message | 7. AJAX search | P1 | SPEC-028 |
| Product stickers: "Хіт", "Топ ціна", discount percentage badge | 9. Stickers | P1 | SPEC-029 |
| One-click "Купити" from card, cart counter in header, cart panel | 3. AJAX cart, 1. Quick order | P1 | SPEC-026 |
| Availability states ("Є в наявності", quantity), notification when absent | 14. Back in stock, 2. Pre-order | P2 | SPEC-026 |
| Recently viewed products block | 10. Viewed products | P2 | SPEC-030 |
| Compact category list in listing sidebar | 8. Side category menu | P2 | SPEC-027 |

- **REQ-MOY-010** `[LIST]` — Mega menu, category wall and side menu follow the behaviour rows above; the reference adds
  two details to the brief: a **brand group inside a category** (e.g. brand first, then its product lines) and **promo
  tiles at the end of a menu section**. Owner: SPEC-027.
- **REQ-MOY-011** `[LIST]` — Search suggestions additionally store **recent queries per visitor** (localStorage, with
  "clear history") and show **popular queries/categories** configured in admin; voice input is optional (P3). Owner: SPEC-028.
- **REQ-MOY-012** `[LIST]` — Stickers support the reference's three kinds: manual label (Хіт, Топ ціна), **automatic
  discount badge `-N%` computed from old/new price**, and a promo tooltip text on the badge. Owner: SPEC-029.
- **REQ-MOY-013** `[LIST]` — Quick buy, AJAX cart, pre-order and back-in-stock follow the brief; availability text
  ("in stock", "n left") comes from the product stock fields. Owner: SPEC-026.
- **REQ-MOY-014** `[LIST]` — Recently viewed follows the brief. Owner: SPEC-030.

## B. Not in the brief (`[NEW]`)

### Header and shell

- **REQ-MOY-020** `[NEW]` P1 — **Announcement bar** above the header: one dismissible message with optional link,
  scheduled by dates, per language. Owner: SPEC-031.
- **REQ-MOY-021** `[NEW]` P1 — **Header utility strip**: language switcher (uk/ru/en), phone(s) with call-centre
  hours, messenger buttons (Telegram, Viber, Messenger links from settings), secondary links (Blog, Promotions, Stores,
  Order tracking, Club), each item optional. Owner: SPEC-031.
- **REQ-MOY-022** `[NEW]` P2 — **City selector** with confirmation prompt ("Is your city Kyiv?"): stores the visitor's
  city; used by delivery/pickup blocks (REQ-MOY-050) and the stores list. List of cities from admin, no geo-IP in v1.
  Owner: SPEC-032.
- **REQ-MOY-023** `[NEW]` P1 — Header icons for **compare, wishlist, account, cart** with counters, and an always-visible
  checkout button in the cart panel. (Core already has compare/wishlist; only theme work.) Owner: SPEC-031.
- **REQ-MOY-024** `[NEW]` P2 — **Mobile shell**: bottom tab bar (Home, Catalogue, Cart, Lists, Profile) on small screens,
  catalogue opens as full-screen panel; desktop layout unchanged. Owner: SPEC-031.
- **REQ-MOY-025** `[NEW]` P3 — AI/chat consultant widget slot (third-party script, loaded after consent, SPEC-017). Owner: SPEC-031.

### Home

- **REQ-MOY-030** `[NEW]` P1 — **Hero/promo slider** (images, link, text, per-language, schedule) beside the catalogue
  menu. Owner: SPEC-033.
- **REQ-MOY-031** `[NEW]` P1 — **Brand strip** (logo links to manufacturers). Owner: SPEC-033.
- **REQ-MOY-032** `[NEW]` P1 — **Product carousels by category** ("Best laptops", "Best smartphones"): a block takes a
  category and a mode (bestsellers/new/discounted/manual list), shows N cards with prev/next. Owner: SPEC-033.
- **REQ-MOY-033** `[NEW]` P2 — **Newsletter subscription block** with one e-mail field and consent line; stored via
  core newsletter flag or an integration (SPEC-018). Owner: SPEC-033.
- **REQ-MOY-034** `[NEW]` P2 — **SEO text block** at the bottom of home/category with "read more" collapse; content from
  the category/home description. Owner: SPEC-033.

### Category listing

- **REQ-MOY-040** `[NEW]` P1 — Sort options: price asc, price desc, new, popular, recommended, **with reviews**; map to
  core sort keys where they exist, otherwise an event-added key (SPEC-007). Owner: SPEC-034.
- **REQ-MOY-041** `[NEW]` P1 — **Load more** button ("Show 24 more") in addition to numbered pagination; the SEO-relevant
  paginated URLs stay crawlable (SPEC-013). Owner: SPEC-034.
- **REQ-MOY-042** `[NEW]` P1 — Listing toolbar shows product count, "reset all filters", and the active filter chips
  (styling of OCFilter output; no own filter). Owner: SPEC-034.
- **REQ-MOY-043** `[NEW]` P2 — Card shows: product code, rating with review count, old price struck through next to the
  new one, discount badge, optional variant chips (e.g. storage sizes) when the product has them, compare and wishlist
  icons. Owner: SPEC-034.
- **REQ-MOY-044** `[NEW]` P2 — Category header tabs/chips for subcategories with images (family of REQ-MOY-010 but on the
  listing page). Owner: SPEC-034.

### Product page

- **REQ-MOY-050** `[NEW]` P1 — **Delivery and payment block** next to the buy button: pickup, courier, post-office
  delivery with price/free threshold and estimated date per selected city; payment methods; warranty and return terms.
  Texts and thresholds come from settings, estimates from a per-city table. Owner: SPEC-032.
- **REQ-MOY-051** `[NEW]` P1 — **Section anchors/tabs**: About, Specifications, Reviews/Questions, Description,
  Availability in stores, Compare, Accessories; sticky on scroll. Owner: SPEC-035.
- **REQ-MOY-052** `[NEW]` P1 — **Key attributes summary** (3-4 chosen attributes) next to the title; manufacturer link in
  breadcrumbs. Owner: SPEC-035.
- **REQ-MOY-053** `[NEW]` P2 — **Questions and answers** (not only reviews) with moderation. Owner: SPEC-035.
- **REQ-MOY-054** `[NEW]` P2 — **Availability in stores / pickup points** per city with "pick up in N minutes" labels,
  fed from the stock-per-location data of SPEC-012 if present. Owner: SPEC-032.
- **REQ-MOY-055** `[NEW]` P2 — **Accessories** tab/carousel (linked products of kind "accessory") and a **similar
  products** carousel (same category + brand). Core "related" covers one list only. Owner: SPEC-035.
- **REQ-MOY-056** `[NEW]` P2 — **Credit / instalments block** ("pay in parts") with monthly-payment calculator driven
  by bank-partner settings; display only, order flow in SPEC-011/018. Owner: SPEC-036.
- **REQ-MOY-057** `[NEW]` P3 — **Trade-in banner** linking to a request form (old device description -> lead e-mail),
  no valuation engine. Owner: SPEC-036.

### Footer and service pages

- **REQ-MOY-060** `[NEW]` P1 — Footer groups: Customers (delivery, payment, returns, warranty), Stores network, Top
  categories, contacts (phones, hours, address), messengers, social links, optional app-store badges. All editable,
  collapsible on mobile. (CSS-only footer of SPEC-023 is replaced by a footer override in the owner spec.) Owner: SPEC-031.
- **REQ-MOY-061** `[NEW]` P2 — **Stores page** (list + map link + hours, per city) and **order tracking** page (order
  number + phone/e-mail -> status from core order history). Owner: SPEC-032.
- **REQ-MOY-062** `[NEW]` P3 — Loyalty club and B2B pages as static content pages only (SPEC-016), no programme logic.

### Exclusions

- **REQ-MOY-090** — Excluded from the template: own native mobile app, bonus-points engine, trade-in valuation, B2B
  price lists, offline store inventory sync, voice search backend. They need their own spec if a client pays for them.

## Non-functional requirements

- **REQ-MOY-080** — Budgets of SPEC-006 REQ-THM-042 hold for the home page with all P1 blocks enabled (CSS <= 150 KB,
  JS <= 60 KB, no third-party render-blocking requests).
- **REQ-MOY-081** — Each module adds at most one DB query per storefront page when enabled and caches its result
  (SPEC-004 REQ-EXT-081, SPEC-019).

## Data requirements

None in this spec. Each owner spec defines its own tables/settings by migration (SPEC-005).

## Interfaces

None; this spec produces follow-up specs.

## Edge cases

- Feature present in reference but impossible in OpenCart without core changes (e.g. unlimited per-city stock) ->
  recorded in the owner spec as a limitation or an extension via events/OCMOD (SPEC-007).
- Two owner specs need the same data (city) -> SPEC-032 owns it; others read it through its library class.
- Reference changes over time -> this spec records the observation date; it is not kept in sync.

## Acceptance criteria

- [ ] Owner approves or strikes out each `[NEW]` requirement and its priority.
- [ ] Every approved requirement has an owner spec created from `_template/` with the next free number.
- [ ] No implementation is committed under SPEC-024 itself.
