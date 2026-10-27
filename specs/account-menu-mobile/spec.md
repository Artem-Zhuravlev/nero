# SPEC-065 — Account Menu Visible on Phones

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-063 (account menu becomes a pill strip on phones)

## Problem

`common/column_right.twig` renders `<aside id="column-right" class="hidden-xs">`; Bootstrap's `.hidden-xs { display: none !important }`
hides the whole account menu below 768px, so the pill strip from SPEC-063 never appears and navigation inside the account is impossible.

## Purpose

The account menu is always reachable on phones as the scrollable pill strip above the content.

## Scope

Theme `ocs` stylesheet, account-menu block (`#column-right/#column-left` containing `.ocs-acc-nav`).

## Out of scope

Other column modules (they stay hidden on phones), menu markup, desktop layout.

## Functional requirements

- **REQ-AMM-001** — A side column containing `.ocs-acc-nav` is displayed on phones despite `.hidden-xs`.
- **REQ-AMM-002** — Side columns without the account menu stay hidden on phones; desktop is unchanged.

## Acceptance criteria

- [x] At 375px the account pages show the pill strip above the content; other pages' side columns remain hidden.
