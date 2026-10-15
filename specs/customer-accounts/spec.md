# SPEC-015 — Customer Accounts, Groups, Privacy and Anti-abuse

**Status:** draft
**Owner:** Artem Zhuravlev
**Last updated:** 2026-10-15
**Refines:** SPEC-011 (guest checkout), SPEC-014 (retention, DSR) · **Facts:** [`docs/ocstore-reference.md`](../../docs/ocstore-reference.md)

## Platform facts (verified in `core/upload/`)

1. `oc_customer`: `customer_group_id`, `store_id`, `language_id`, names, `email`, `telephone`, `fax`, `password varchar(40)` +
   `salt varchar(9)` (legacy salted-SHA1 scheme written by `catalog/model/account/customer.php`, `salt = token(9)`),
   `cart text` and `wishlist text` (serialized), `newsletter`, `address_id`, `custom_field text`, `ip`, `status`, `safe`
   (skips fraud check), `token`, `code` (reset code), `date_added`.
2. Account routes (`catalog/controller/account/`): `register, login, logout, forgotten, reset, account, edit, password,
   address, order, return, reward, transaction, download, newsletter, wishlist, voucher, recurring, affiliate, tracking, success`.
3. Registration shows the account terms link from `config_account_id` and requires the `agree` checkbox when set;
   custom fields are validated per customer group through `register/customfield`.
4. Related admin controllers: `customer/{customer,customer_group,custom_field,customer_approval}`.
5. Settings: `config_customer_group_id` (1), `config_customer_group_display` (["1"]), `config_account_id` (3),
   `config_login_attempts` (5), `config_captcha` (`basic`) and `config_captcha_page` (["review","return","contact"]),
   `config_customer_online|activity|search` (0), `config_affiliate_*`.
6. Captcha extensions: `basic`, `google` (type `captcha`). Activity tables: `customer_activity`, `customer_ip`,
   `customer_login`, `customer_online`, `customer_search`, `customer_reward`, `customer_transaction`, `customer_history`,
   `customer_approval`.
7. Customer login lookup and password check live in `system/library/cart/customer.php` (login by lower-cased e-mail).

## Problem

Customer data is personal data. The core stores weak password hashes, lets a default store anyone register,
logs IPs and activity depending on settings, and has no explicit consent trail or deletion workflow.

## Purpose

Define the customer model for this store (groups, fields, guest vs registered), registration/login rules,
anti-abuse controls, consent and privacy handling, and how customer-related extensions behave.

## Scope

Customer groups and custom fields as data, registration/login/reset policy, validation rules, anti-abuse,
consent records, account page extensions, wishlist/cart persistence, privacy tooling (with SPEC-014 DSR).

## Out of scope

Loyalty programme specifics, B2B pricing (needs its own spec), social login providers.

## Functional requirements

### Groups and fields

- **REQ-CUS-001** — Customer groups are declared in `custom/customers/groups.json` (name per language, description,
  `approval` flag, `company_id_display` etc. as core group fields) and applied by an idempotent migration through the
  core admin model (SPEC-012 AdminBoot); group ids of existing groups never change. `config_customer_group_id`
  (default group for registration/guest) and `config_customer_group_display` (groups selectable at registration) are
  set by that migration.
- **REQ-CUS-002** — Custom fields (`oc_custom_field*`) are declared in `custom/customers/fields.json` (location
  `account|address`, type, per-language names/values, validation regex, required flag, groups, sort) and applied via
  admin models; field ids are mapped to codes with `oc_ocs_catalog_code` (SPEC-012 REQ-CAT-002) with entity types
  `custom_field`, `custom_field_value`.
- **REQ-CUS-003** — Registration fields and their required/optional status follow core validation (`account/register::validate`, first/last
  name length 1–32, telephone 3–32, e-mail, password 4–20, custom fields per group). Relaxing or tightening a rule
  is done only by a `controller/account/register/before` validation hook returning core's error structure, with the
  rule recorded in the store-config spec; the default is to keep core rules.

### Registration, login, password

- **REQ-CUS-010** — E-mail is the identifier: lower-cased, trimmed, validated by core `filter_var`; duplicates refused
  by core; plus-addressing allowed; disposable-domain deny-list `custom/customers/deny-domains.txt` checked by an event
  on `catalog/model/account/customer/addCustomer/before` (returns an error via the controller `validate` path —
  implemented as a `controller/account/register/before` validation hook that returns core's error structure).
- **REQ-CUS-011** — Password policy: >= 10 characters, not equal to e-mail, not in the common-password list (shared with
  SPEC-010). Because core hashes with salted SHA1, the extension `ocs_customer_security` upgrades hashes on next login:
  on successful login (`catalog/model/account/customer/getCustomer…` event or login controller `after`) it stores a
  `password_hash(PASSWORD_DEFAULT)` value in `oc_ocs_customer_secret(customer_id, hash, algo, date_updated)` and from then on
  verifies against it first; the legacy columns remain filled for core compatibility **only until core code paths no longer
  need them** — decision recorded in the spec that implements it, since core login (`cart/customer.php`) compares the legacy
  columns itself: the upgrade therefore requires an OCMOD exception on that file, to be approved explicitly under SPEC-007
  REQ-OCM-003/002. Until approved, compensating controls apply (REQ-CUS-020…023).
- **REQ-CUS-012** — Password reset uses core `forgotten/reset` (code in `oc_customer.code`); reset links expire in 1 hour
  (an event rejects codes older than that using a timestamp stored in `oc_ocs_customer_reset`); response to "forgotten" is
  identical whether the e-mail exists or not (message text from language file).
- **REQ-CUS-013** — E-mail verification on registration is optional and decided in the store-config spec; if on,
  customers have `status=0` until verified through a signed link (`HMAC(config_encryption, customer_id|email|expiry)`).

### Anti-abuse

- **REQ-CUS-020** — Login attempts: core `config_login_attempts` stays (>= 5); an additional per-IP throttle (30 login POST/hour)
  in `ocs_customer_security` using cache counters; HTTP 429 with localized message.
- **REQ-CUS-021** — Captcha: enabled for registration, login after 3 failures, contact, review, return (`config_captcha_page`
  set by migration); provider choice (`basic` or `google` reCAPTCHA) and keys via settings (secret keys masked in admin).
  Honeypot field + minimum submit time (3 s) added by view/controller events on register and contact forms.
- **REQ-CUS-022** — Registration rate limit: 5 accounts per IP per day and global 200/hour (counters); beyond -> challenge.
- **REQ-CUS-023** — Enumeration resistance: login errors identical for unknown e-mail and wrong password; timing equalised by
  running a dummy hash verification.

### Consent and privacy

- **REQ-CUS-030** — Consent records: table `oc_ocs_consent(consent_id, customer_id NULL, email_hash, purpose, version,
  accepted TINYINT, ip_hash, user_agent_hash, date_added)`; purposes: `terms`, `privacy`, `marketing_email`,
  `marketing_sms`. Written on registration (agree checkbox), checkout (terms), newsletter subscribe; versions come from
  `custom/customers/consents.json` with the information page version/date.
- **REQ-CUS-031** — Newsletter (`oc_customer.newsletter`) changes are mirrored into consent rows; unsubscribe link works without login
  (signed token) and is present in every marketing e-mail.
- **REQ-CUS-032** — IPs are stored only where core needs them (`customer.ip`, `customer_ip`, order `ip`); consent/audit rows store hashes
  (`sha256(ip + daily salt)`). `customer_activity`/`customer_search`/`customer_online` stay off unless a spec needs them.
- **REQ-CUS-033** — Customer account page offers: download my data (JSON, via SPEC-014 DSR export for the logged-in customer),
  delete my account (queues an anonymisation request processed by the DSR tool; orders retained anonymised per retention).
  Implemented in `ocs_customer_privacy` through theme template overrides + controller `extension/ocs_customer_privacy/*`.

### Guest vs registered

- **REQ-CUS-040** — Guest checkout allowed (`config_checkout_guest=1`) per SPEC-011; guest orders store `customer_id=0`; a guest
  who later registers with the same e-mail may claim past orders after verifying e-mail (`ocs_customer_privacy`
  link/claim flow, matching by verified e-mail only).
- **REQ-CUS-041** — Wishlist/cart persistence: core stores `wishlist`/`cart` serialised in `oc_customer`; custom code
  does not parse these columns directly (use model methods).

### Customer extensions rules

- **REQ-CUS-050** — Extensions adding customer data declare `personalData` in `extension.json` (SPEC-014 REQ-OPS-051) and implement
  an `export($customer_id)` / `anonymise($customer_id)` method in their library, called by the DSR tool.
- **REQ-CUS-051** — Customer-facing e-mails use theme mail templates; no customer data in URLs (tokens only).

## Non-functional requirements

- **REQ-CUS-060** — Login/register request overhead from our extensions < 15 ms excluding hashing.
- **REQ-CUS-061** — All customer-facing strings localised (SPEC-008).

## Data requirements

New tables: `oc_ocs_customer_secret` (pending approval), `oc_ocs_customer_reset`, `oc_ocs_consent`;
JSON sources in `custom/customers/`.

## Edge cases

- Customer registers while holding a guest cart: core merges the cart; extensions must not break the merge.
- Two tabs submit registration -> core duplicate e-mail check wins; second gets standard error.
- DSR anonymisation of a customer with open orders -> refused until orders are closed (message to operator).
- Consent version bumps -> existing customers are re-prompted at next login (flag in session), not blocked.
- Language switch during reset flow -> link language follows customer `language_id`.

## Acceptance criteria

- [ ] Groups/fields migration idempotent; ids stable.
- [ ] Throttle test: 31st login POST in an hour from one IP returns 429.
- [ ] Reset code older than 1 h is rejected; unknown e-mail response identical to known.
- [ ] Consent rows created for registration and newsletter toggle.
- [ ] Export returns the customer's data from core and declared extension tables; anonymise removes PII.
