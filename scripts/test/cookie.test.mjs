// SPEC-083 cookie popup module checks.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
const E = 'custom/extensions/ocs_cookie';

// TEST-CKP-003 - verifies REQ-CKP-003, REQ-CKP-013, REQ-CKP-014 (footer prints a hidden dialog with both buttons; reopen hook)
test('cookie: footer template prints the hidden dialog', () => {
  const f = read('custom/theme/ocs/template/common/footer.twig');
  assert.match(f, /\{% if ocs_cookie %\}[\s\S]*id="ocs-cookie" role="dialog"[\s\S]*\shidden>/);
  assert.match(f, /data-ocs-cookie="all"/);
  assert.match(f, /data-ocs-cookie="necessary"/);
  assert.match(f, /data-ocs-cookie-reopen/);
});

// TEST-CKP-004 - verifies REQ-CKP-005, REQ-CKP-006 (cookie name, public hook and event)
test('cookie: theme JS stores ocs_consent and exposes the hook', () => {
  const js = read('custom/theme/ocs/javascript/theme.js');
  assert.match(js, /NAME = 'ocs_consent'/);
  assert.match(js, /window\.ocsConsent = \{/);
  assert.match(js, /new CustomEvent\('ocs:consent'/);
});

// TEST-CKP-005 - verifies REQ-CKP-008 (fixed, no layout shift, lifted above the tab bar on phones)
test('cookie: styles', () => {
  const css = read('custom/theme/ocs/stylesheet/stylesheet.css');
  for (const sel of ['.ocs-cookie[hidden]', '.ocs-cookie--bar', '.ocs-cookie-actions']) assert.ok(css.includes(sel), sel);
  assert.match(css, /\.ocs-cookie \{[^}]*position: fixed/);
  assert.match(css, /inset-block-end: calc\(64px/);
});

// TEST-CKP-006 - verifies REQ-CKP-001, REQ-CKP-002 (admin validation and manifest)
test('cookie: admin validation and manifest', () => {
  const ext = JSON.parse(read(`${E}/extension.json`));
  assert.equal(ext.code, 'ocs_cookie');
  assert.equal(ext.spec, 'SPEC-083');
  const a = read(`${E}/upload/admin/controller/extension/module/ocs_cookie.php`);
  assert.match(a, /error\['number'\]/);
  assert.match(a, /editSetting\('module_ocs_cookie'/);
});

// TEST-CKP-007 - verifies REQ-CKP-013 (language files have identical keys in every language)
test('cookie: strings are identical across languages', () => {
  const keys = (side, file, l) => [...read(`${E}/upload/${side}/language/${l}/${file}`).matchAll(/\$_\['([a-z_]+)'\]/g)].map((m) => m[1]).sort();
  const cat = 'extension/ocs_cookie/cookie.php';
  for (const l of ['en-gb', 'ru-ru']) assert.deepEqual(keys('catalog', cat, l), keys('catalog', cat, 'uk-ua'));
  const adm = 'extension/module/ocs_cookie.php';
  assert.deepEqual(keys('admin', adm, 'en-gb'), keys('admin', adm, 'uk-ua'));
});
