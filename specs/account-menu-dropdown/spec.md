# SPEC-066 — Account Menu as a Dropdown on Phones

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-063, SPEC-065

## Problem

On phones the account menu is a horizontal pill strip (SPEC-063); with 15 items it is awkward to scroll and the current page is easy to lose.

## Purpose

On phones the account menu is a dropdown above the page content: a button with the current section opens the full list.

## Scope

Theme `ocs`: stylesheet (account-menu phone block) and `theme.js` (account area script).

## Out of scope

Desktop sidebar, menu markup/links, other modules.

## Functional requirements

- **REQ-AMD-001** — At page width <= 56rem a toggle button showing the current section label is placed above the content; the menu is collapsed by default.
- **REQ-AMD-002** — Clicking the button opens/closes the menu list (vertical, full width) and updates `aria-expanded`.
- **REQ-AMD-003** — Desktop layout is unchanged; without JS the menu stays visible.

## Acceptance criteria

- [x] At 375px the account pages show a dropdown button above the content; tapping it lists all menu items.
- [x] Desktop sidebar unchanged.
