// SPEC-082 top bar module checks.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
const E = 'custom/extensions/ocs_topbar';

// TEST-TBR-004 - verifies REQ-TBR-007, REQ-TBR-006 (header prints the top bar first and falls back to the old announcement; button mode has styles)
test('topbar: header template prefers the top bar and keeps the legacy announcement', () => {
  const h = read('custom/theme/ocs/template/common/header.twig');
  assert.match(h, /\{% if ocs_topbar %\}[\s\S]*\{% elseif ocs_shell and ocs_shell\.announcement %\}/);
  assert.match(h, /ocs-announce-btn ocs-announce-btn--\{\{ ocs_topbar\.button\.style \}\}/);
  assert.match(h, /id="ocs-announce" data-id="\{\{ ocs_topbar\.id \}\}"/);
  const css = read('custom/theme/ocs/stylesheet/stylesheet.css');
  for (const sel of ['.ocs-announce--dark', '.ocs-announce--light', '.ocs-announce-btn--solid', '.ocs-announce-btn--outline', '.ocs-announce-row']) {
    assert.ok(css.includes(sel), sel);
  }
});

// TEST-TBR-005 - verifies REQ-TBR-001, REQ-TBR-004 (admin validates dates, colour and half-filled buttons)
test('topbar: admin validation and manifest', () => {
  const ext = JSON.parse(read(`${E}/extension.json`));
  assert.equal(ext.code, 'ocs_topbar');
  assert.equal(ext.spec, 'SPEC-082');
  const a = read(`${E}/upload/admin/controller/extension/module/ocs_topbar.php`);
  assert.match(a, /\^#\[0-9a-fA-F\]\{6\}\$/);
  assert.match(a, /error\['button'\]/);
  assert.match(a, /editSetting\('module_ocs_topbar'/);
});

// TEST-TBR-006 - verifies REQ-TBR-013 (language files have identical keys in every language)
test('topbar: strings are identical across languages', () => {
  const keys = (side, file, l) => [...read(`${E}/upload/${side}/language/${l}/${file}`).matchAll(/\$_\['([a-z_]+)'\]/g)].map((m) => m[1]).sort();
  const cat = 'extension/ocs_topbar/topbar.php';
  for (const l of ['en-gb', 'ru-ru']) assert.deepEqual(keys('catalog', cat, l), keys('catalog', cat, 'uk-ua'));
  const adm = 'extension/module/ocs_topbar.php';
  assert.deepEqual(keys('admin', adm, 'en-gb'), keys('admin', adm, 'uk-ua'));
});
