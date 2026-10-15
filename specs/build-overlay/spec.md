# SPEC-003 — Build Overlay and Generated Configuration

**Status:** in-progress
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-15
**Refines:** SPEC-001 sections 2, 3, 7 · **Facts:** [`docs/ocstore-reference.md`](../../docs/ocstore-reference.md) §1, §8

## Problem

SPEC-001 says "compose `core/` + `custom/` into `build/www`". An implementer needs the exact
algorithm, file lists, template contents, determinism rules and failure modes, otherwise two
implementers produce two different docroots.

## Purpose

Specify `scripts/build.mjs` and the config templates precisely enough that the output is
reproducible byte-for-byte from the same inputs.

## Scope

Build inputs/outputs, copy algorithm, collision rules, generated files (`config.php`,
`admin/config.php`, `.htaccess`, `BUILD_INFO.json`), storage skeleton, admin directory rename,
incremental behaviour, CLI interface, exit codes, logging.

## Out of scope

Running/serving the build, installing the DB, hosting.

## Inputs and outputs

| Input | Role |
|-------|------|
| `core/upload/**` | vanilla tree |
| `custom/overlay/**` | extra files (new paths only) |
| `custom/theme/ocs/**` | -> `catalog/view/theme/ocs/**` |
| `custom/extensions/*/upload/**` | -> docroot, per extension |
| `custom/config/config.php.tpl`, `admin-config.php.tpl` | config templates |
| `.env` | environment values (SPEC-001 §7) |

| Output | |
|--------|--|
| `build/www/**` | docroot |
| `build/BUILD_INFO.json` | provenance |
| `<STORAGE_DIR>/**` | storage skeleton (outside docroot) |

## Functional requirements

### Source selection

- **REQ-BLD-001** — The core tree is read from `core/upload/` only. Excluded from the copy:
  `install/` (whole directory), `config-dist.php`, `admin/config-dist.php`, `.htaccess.txt` (it is
  transformed, REQ-BLD-032), `php.ini` (core's sample ini is not served; PHP settings belong to
  the runtime), and the entire `system/storage/` subtree (relocated, REQ-BLD-040).
- **REQ-BLD-002** — File enumeration is sorted lexicographically by relative POSIX path and
  uses `/` separators internally on every OS; symlinks are not followed and are an error.
- **REQ-BLD-003** — Overlay precedence, in order, later never replaces earlier: core -> overlay
  -> theme -> extensions (alphabetical by directory name). A path that already exists from an
  earlier layer is a **collision** (REQ-BLD-010) regardless of identical content.

### Collision rule

- **REQ-BLD-010** — On collision the build prints `COLLISION <path> (<layer A> vs <layer B>)` for
  every collision, then exits with code 2 without modifying `build/www`.
- **REQ-BLD-011** — The only paths allowed to be generated (not collide) are those listed in
  REQ-BLD-030…034; they are excluded from layers and produced last.

### Path rewriting

- **REQ-BLD-020** — If `ADMIN_DIR` != `admin`, every path starting with `admin/` from any layer is
  written under `<ADMIN_DIR>/` instead. `ADMIN_DIR` must match `^[a-z0-9][a-z0-9_-]{2,31}$` and must
  not equal any top-level docroot directory (`catalog`, `system`, `image`, …) or a reserved
  word (`install`, `index`, `config`).
- **REQ-BLD-021** — Paths inside an extension's `upload/admin/…` are rewritten the same way.
  Template contents are not rewritten (core never hard-codes the literal, see reference §1).

### Generated files

- **REQ-BLD-030** — `config.php` is rendered from `custom/config/config.php.tpl`. The template
  reproduces the stock installer output (reference §1) with these substitutions only:
  `HTTP_SERVER`/`HTTPS_SERVER` = `{{APP_URL}}`; `DIR_APPLICATION` = `{{DOCROOT}}catalog/`;
  `DIR_SYSTEM` = `{{DOCROOT}}system/`; `DIR_IMAGE` = `{{DOCROOT}}image/`; `DIR_STORAGE` =
  `{{STORAGE_DIR}}`; `DIR_LANGUAGE` = `DIR_APPLICATION.'language/'`; `DIR_TEMPLATE` =
  `DIR_APPLICATION.'view/theme/'`; `DIR_CONFIG`, `DIR_CACHE`, `DIR_DOWNLOAD`, `DIR_LOGS`,
  `DIR_MODIFICATION`, `DIR_SESSION`, `DIR_UPLOAD` relative to `DIR_STORAGE`; `DB_DRIVER` =
  `mysqli`; `DB_HOSTNAME/USERNAME/PASSWORD/DATABASE/PORT/PREFIX` = `{{DB_*}}`.
- **REQ-BLD-031** — `<ADMIN_DIR>/config.php` from `admin-config.php.tpl`: `HTTP_SERVER`/`HTTPS_SERVER`
  = `{{APP_URL}}{{ADMIN_DIR}}/`; `HTTP_CATALOG`/`HTTPS_CATALOG` = `{{APP_URL}}`; `DIR_APPLICATION` =
  `{{DOCROOT}}{{ADMIN_DIR}}/`; `DIR_CATALOG` = `{{DOCROOT}}catalog/`; `DIR_TEMPLATE` =
  `DIR_APPLICATION.'view/template/'`; remaining constants as REQ-BLD-030; plus
  `OPENCART_SERVER` and `OPENCARTFORUM_SERVER` unchanged from stock.
- **REQ-BLD-032** — `.htaccess` is `core/upload/.htaccess.txt` with: `RewriteBase` set to the
  path component of `APP_URL`; the rule `RewriteRule ^system/storage/(.*) index.php?route=error/not_found [L]`
  kept; a deny block added for `\.(sql|env|git|md|json|lock|sh|bak)$`, `^(custom|specs|docs|scripts|var|build|core)/`; and
  the `FilesMatch` deny rule left as stock.
- **REQ-BLD-033** — `robots.txt` is taken from core unless `custom/overlay/robots.txt` exists
  (documented exception to REQ-BLD-003: robots.txt may be replaced by overlay).
- **REQ-BLD-034** — `build/BUILD_INFO.json`: `{ "core": "ocStore 3.0.3.7 0002", "gitCommit": "<sha|null>",
  "gitDirty": bool, "builtAt": "<ISO-8601 UTC>", "appEnv": "...", "adminDir": "...",
  "extensions": [{"code","version"}], "theme": "ocs", "files": <count>, "treeHash": "<sha256>" }`.
  It contains no secrets, no absolute paths, no URLs with credentials.
- **REQ-BLD-035** — Template rendering: only `{{KEY}}` tokens (`[A-Z0-9_]+`) are substituted; an
  unknown token, an empty required value, or a leftover `{{` after rendering aborts (exit 3).
  Values are PHP-single-quote-escaped (`\` and `'`) when placed inside `'…'` literals; the
  template author never needs to escape.

### Storage skeleton

- **REQ-BLD-040** — `STORAGE_DIR` (absolute, ends with `/`, not inside `build/www`, not inside
  the repo's tracked tree) is populated from `core/upload/system/storage/`, **including `vendor/`**
  (Twig/ScssPhp autoload is loaded from `DIR_STORAGE`, reference §8) and the `index.html`
  guard files. Existing files are never overwritten or deleted except `modification/` which is
  cleared by `refresh` (SPEC-005), not by build.
- **REQ-BLD-041** — Subdirectories `cache logs modification session upload download` must exist
  and be writable by the PHP user; build verifies writability and fails with the path if not.
- **REQ-BLD-042** — `vendor/` is synchronised with core on every build (content-compare) so a core
  upgrade is picked up; extra vendor packages added by us live in `custom/storage-vendor/` and
  are layered over it (collision rule applies).

### Incremental behaviour

- **REQ-BLD-050** — Copy a file only if the destination is missing or its SHA-256 differs; set no
  special mtimes. Report counts `added/updated/unchanged/removed`.
- **REQ-BLD-051** — Files present in `build/www` but not produced by the current layers are deleted
  (stale cleanup) **except** under `image/catalog/`, `image/cache/` and `image/data/` (runtime
  uploads/thumbs), and except the generated files of REQ-BLD-030…034.
- **REQ-BLD-052** — Two consecutive builds with unchanged inputs report `added=0 updated=0
  removed=0` and leave `treeHash` identical (idempotence).
- **REQ-BLD-053** — The build writes into `build/.tmp-<pid>/` first for generated files and
  renames atomically; a failed build leaves the previous `build/www` untouched when possible
  (collision/template errors happen before any write).

### CLI

- **REQ-BLD-060** — `node scripts/build.mjs [--env-file <path>] [--out <dir>] [--check] [--verbose]`.
  `--check` performs steps 1–3 (collect, collide, render to memory) and writes nothing; used by
  verify. Default out: `build/www`.
- **REQ-BLD-061** — Exit codes: 0 ok · 1 usage/IO error · 2 collision · 3 template/env error ·
  4 storage not writable · 5 core manifest mismatch (REQ-ARCH-010 is checked first).
- **REQ-BLD-062** — Output is line-oriented, stable, free of timestamps except in
  `BUILD_INFO.json`, so diffs between builds are meaningful.

## Non-functional requirements

- **REQ-BLD-070** — Build of the full tree completes in < 60 s on a laptop; memory use bounded
  (stream copies; no whole-tree buffers).
- **REQ-BLD-071** — No network access, no package installation during build.
- **REQ-BLD-072** — Node built-ins only (`fs`, `path`, `crypto`, `child_process` for `git`).

## Data requirements

None (filesystem only).

## Edge cases

- `ADMIN_DIR=admin` -> no rewrite, no collision with core's `admin/` (core layer owns it).
- Extension tries to add `catalog/controller/product/product.php` -> COLLISION.
- Theme file `catalog/view/theme/default/...` in `custom/theme/ocs` -> impossible: theme layer
  is rooted at `catalog/view/theme/ocs/`, a path outside that root in the theme dir is an error.
- `.env` has `STORAGE_DIR` relative -> resolved against repo root, then must satisfy REQ-BLD-040.
- Windows path separators / reserved names (`con`, `nul`) in custom tree -> rejected.
- Case-only differences in paths (`Foo.php` vs `foo.php`) -> collision on case-insensitive FS and
  on Linux alike: build compares lower-cased paths.

## Acceptance criteria

- [ ] Output has no `install/`, no `config-dist.php`, no `.htaccess.txt`, no `system/storage/`.
- [ ] Generated configs contain every constant of reference §1 and no `{{`.
- [ ] Idempotence test (REQ-BLD-052) and collision negative test (REQ-BLD-010) pass.
- [ ] `ADMIN_DIR=backoffice` yields `build/www/backoffice/` with correct `HTTP_SERVER`.
- [ ] `image/catalog` content survives a rebuild.
