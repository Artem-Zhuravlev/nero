# SPEC-076 — Image Resize Guard (OCMOD) and Migration 0008 Fix

**Status:** implemented
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-27
**Refines:** SPEC-006 (theme, REQ-THM-030), SPEC-005 (migrations), SPEC-007 (OCMOD)

## Problem

On a live site the storefront fataled with `Image::resize() must be of the type int, null given`: the theme image size settings were empty and the event guard from migration 0009 did not run on every deployment. Separately, migration 0008 called `setting->delete()` with several keys in one call, which does not match the helper's `(code, key)` signature.

## Purpose

Make `ModelToolImage::resize()` safe by patching it directly, and correct the 0008 settings cleanup.

## Scope

`custom/ocmod/ocs_image_resize.ocmod.xml`, `custom/db/migrations/0010_image_resize_ocmod.php`, `custom/db/migrations/0008_remove_cities.php`, theme archive, `specs/README.md`.

## Out of scope

Image sizes configuration UI, the 0009 event guard, `core/`.

## Functional requirements

- **REQ-IRG-001** — An OCMOD patches `catalog/model/tool/image.php` so that a null/0 width or height falls back to the theme's configured product image size, or 228 px if that is empty.
- **REQ-IRG-002** — Migration 0010 installs the OCMOD idempotently; rollback is removing the modification `ocs_image_resize` in admin and refreshing modifications.
- **REQ-IRG-003** — Migration 0008 deletes the `module_ocs_city*` settings by `(code, key)` pairs, one call per key, with the same end result as intended.

## Non-functional requirements

- **REQ-IRG-004** — The OCMOD header states purpose, owner and why an event is insufficient; `npm run verify` green; theme archive rebuilt (`npm run pack:theme`).

## Acceptance criteria

- [ ] After refresh, `resize($f, 0, null)` no longer raises a type error.
- [ ] Running migrations twice changes nothing the second time.
