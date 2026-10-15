# SPEC-010 — Admin Access, Users, Permissions and API Access

**Status:** draft
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-15
**Refines:** SPEC-001 section 10 · **Facts:** [`docs/ocstore-reference.md`](../../docs/ocstore-reference.md) §2, §5

## Platform facts (verified in `core/upload/`)

1. Admin auth: `system/library/cart/user.php::login()` accepts a password if it equals
   `SHA1(salt . SHA1(salt . SHA1(password)))` **or** `md5(password)` (legacy), for `status=1` users.
   There is no bcrypt/argon support, no 2FA, no lockout for admin logins in core code read here.
2. Every admin request except an ignore-list needs `user_token` in GET equal to the session token
   (`admin/controller/startup/login.php`); permission check is `startup/permission` on
   `access` (route cut to 2 segments, or 3 for `extension/<type>/<code>`).
3. `oc_user`: `username varchar(20)`, `password varchar(40)`, `salt varchar(9)`, `user_group_id`,
   `email`, `ip`, `status`. `oc_user_group.permission` is JSON `{"access":[…],"modify":[…]}`;
   default groups: `1 Administrator` (all routes), `10 Demonstration` (empty permission).
4. ocStore adds `permission['hiden']` (sic) for "hide modules per user group" in extension lists
   (`admin/controller/extension/extension/module.php`).
5. API (used by admin order editing): `oc_api` (default user `Default`, 256-char key created by the
   installer), IP whitelist `oc_api_ip`, sessions `oc_api_session` (1-hour sliding), token `api_token`.
   Catalog `startup/session` validates `api_token` + remote IP.
6. Customer-side: `config_login_attempts` (default 5) throttles customer logins.

## Problem

The admin panel is the highest-value target and its built-in protections are weak (SHA1 hashes,
no 2FA, predictable `/admin/` path, default API key). Staff roles also need least privilege, and
permissions must be reproducible from git rather than clicked together.

## Purpose

Define how admin users, groups and permissions are modelled and provisioned, how the admin is
hardened without editing core, and how the API user is handled.

## Scope

Roles catalogue, permission provisioning, user provisioning, hardening measures (extension +
events + web server), API user policy, audit trail, password policy, session policy.

## Out of scope

Customer accounts (SPEC-011), hosting-level firewalling.

## Functional requirements

### Roles and permissions

- **REQ-ACC-001** — Roles are declared in `custom/access/roles.json`:
  ```json
  { "roles": [ { "name": "Administrator", "id": 1, "inherit": "all" },
               { "name": "Content manager", "access": ["catalog/*","blog/*","design/*"],
                 "modify": ["catalog/*","blog/*"] },
               { "name": "Order manager", "access": ["sale/*","customer/customer","report/*"],
                 "modify": ["sale/order","sale/return"] } ] }
  ```
  Wildcards `x/*` expand at provision time to every existing admin controller route under `x/`
  (including extension routes of types registered). `Administrator` (id 1) is never edited
  by the provisioner except to append new routes.
- **REQ-ACC-002** — Migration `0004_roles` provisions groups from `roles.json` via
  `$m->permission->grant` (SPEC-005): creates missing groups (keeping ids of existing ones), merges
  routes, **never removes** routes automatically (removal is an explicit `revoke` migration).
- **REQ-ACC-003** — Principle of least privilege: `modify` ⊆ `access`; reports and settings
  (`setting/*`, `user/*`, `tool/*`, `marketplace/*`, `design/theme`, `common/developer`,
  `extension/*`) are Administrator-only unless a spec grants otherwise.
- **REQ-ACC-004** — Every extension install migration grants its routes to Administrator
  (REQ-EXT-050) and, if the owning spec says so, to named roles; no extension ever grants itself to
  all groups.
- **REQ-ACC-005** — The `Demonstration` group (id 10) is left with empty permissions and no users.
- **REQ-ACC-006** — Core extensions that the store does not use (payment/shipping/modules shipped
  in the distribution, e.g. the long list in `admin/controller/extension/payment/*`) are neither
  installed nor visible to non-admins; the ocStore `hiden` permission list is used to hide them
  from the Administrator's extension lists via migration (`hide_modules` list in
  `custom/access/hidden-extensions.json`).

### Users

- **REQ-ACC-010** — Personal accounts only: one `oc_user` per person; no shared logins; the installer's
  initial user is renamed/replaced, never used for daily work in production. Users are created in
  admin (not in git) — git holds roles, not people or passwords.
- **REQ-ACC-011** — Password policy (enforced by an admin `model/user/user/editPassword|addUser` model
  event + an OCMOD-free check, SPEC-007): >= 14 characters, not equal to username/email, checked
  against a local deny-list of the 10 000 most common passwords. Because core stores SHA1+salt,
  the event handler additionally re-hashes nothing (can't), so compensating controls
  REQ-ACC-020…024 are mandatory.
- **REQ-ACC-012** — Inactive admin users are disabled (`status=0`) by an operational runbook step
  after 90 days (SPEC-014); the list is produced by the `last login` audit table (REQ-ACC-030).

### Hardening (compensating controls)

- **REQ-ACC-020** — `ADMIN_DIR` is not `admin` outside local (SPEC-003 REQ-BLD-020).
- **REQ-ACC-021** — Admin is served over HTTPS only; web server config redirects `http`->`https` and
  sends HSTS; `config_secure`/`config_ssl` match.
- **REQ-ACC-022** — Optional IP allow-list for the admin directory at web server level (documented
  `.htaccess` `Require ip` snippet generated by the build when `ADMIN_ALLOW_IPS` is set in `.env`).
- **REQ-ACC-023** — Login throttling and lockout for admin: extension `ocs_admin_guard` (library
  type) hooks `admin/controller/common/login/before` (event), counts failures per `(ip, username)`
  in `oc_ocs_admin_guard_attempt` (5 failures / 15 min -> block 15 min, doubling), logs to
  `DIR_LOGS/error.log` with `[ocs_admin_guard]`, and never reveals whether the username exists.
- **REQ-ACC-024** — Second factor (TOTP) via the same extension: `oc_ocs_admin_guard_totp(user_id,
  secret_enc, enabled)`; secret encrypted with `config_encryption`; hooks
  `admin/controller/common/login/after`-style flow by redirecting to its own verification route
  until the session flag `ocs_admin_guard_ok` is set; **mandatory for Administrator** in
  non-local environments; recovery codes (10, single use, stored hashed with `password_hash`).
  The 2FA feature requires its own spec before implementation (`SPEC-0NN admin-2fa`); this spec
  reserves the extension name and tables.
- **REQ-ACC-025** — Session policy: admin session cookie flags `Secure; HttpOnly; SameSite=Lax` set
  by PHP ini in the generated runtime config (`session.cookie_secure=1` etc.) — documented in the
  production profile; idle timeout 30 min enforced by the guard extension (compares last activity).
- **REQ-ACC-026** — `common/filemanager`/`tool/upload` access limited to Administrator and Content
  manager roles; allowed extensions and MIME lists (`config_file_ext_allowed`,
  `config_file_mime_allowed`) are set by migration to a safe list (images, pdf, zip) with no
  `php|phtml|phar|html|htm|js|svg` (svg only if sanitised, own decision).

### API user

- **REQ-ACC-040** — The installer-created API user `Default` and its key are treated as compromised
  on creation: a migration sets `status=0` for it unless a spec needs API order editing; if enabled, its key
  is regenerated (`bin2hex(random_bytes(32))`) by migration, stored only in the DB, and its IP
  list (`oc_api_ip`) contains only the server's own addresses. `config_api_id` is kept consistent.
- **REQ-ACC-041** — Admin order editing uses this API from the admin session; if unused, the
  `sale/order` edit flow is the only consumer, and disabling the API disables order editing
  (documented consequence).

### Audit

- **REQ-ACC-030** — Table `oc_ocs_admin_audit (audit_id, user_id, username, ip, route, action,
  object_type, object_id, date_added)` is filled by events on admin model calls that mutate
  sensitive data (`user/*`, `setting/setting/editSetting`, `extension/*`, `sale/order/*`
  status changes, `design/*`, `marketplace/*`). Retention 400 days. No passwords or secrets in rows.
- **REQ-ACC-031** — Login success/failure events are recorded with ip and user agent hash.

## Non-functional requirements

- **REQ-ACC-050** — Guard checks add < 5 ms to an admin request; they run only on login and on
  state-changing routes.
- **REQ-ACC-051** — No lockout of the break-glass procedure: a documented CLI script
  `scripts/admin-unlock.php --user <name>` clears guard state and disables 2FA for a user (requires
  shell access to the server).

## Data requirements

`oc_user_group.permission`, `oc_user`, `oc_api*` (core); new tables
`oc_ocs_admin_guard_attempt`, `oc_ocs_admin_guard_totp`, `oc_ocs_admin_audit`.

## Edge cases

- A route in `roles.json` that does not exist yet -> provisioning warns; verify fails if a
  committed role lists an unknown non-wildcard route.
- Extension installed later -> wildcard roles do not auto-gain it; the extension's migration
  grants explicitly (REQ-ACC-004).
- Two admins edit permissions simultaneously -> last write wins (core); provisioner re-reads JSON
  inside a lock (`GET_LOCK`).
- User group JSON contains the ocStore-specific `hiden` key -> preserved by the provisioner.

## Acceptance criteria

- [ ] `migrate up` creates the roles from `roles.json`; running twice changes nothing.
- [ ] A Content manager cannot open `sale/order` (403 page) nor `user/user`.
- [ ] Guard: 6th wrong password within 15 min blocks, even with the correct one.
- [ ] Default API user is disabled; `config_api_id` unchanged and consistent.
- [ ] Audit rows appear for a setting change and a user creation, without secrets.
