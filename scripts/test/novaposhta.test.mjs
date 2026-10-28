// SPEC-085 Nova Poshta delivery checks.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
const E = 'custom/extensions/ocs_novaposhta';
// the browser script exports its pure helpers when a CommonJS `module` is present
const mod = { exports: {} };
new Function('module', read(`${E}/upload/catalog/view/javascript/ocs/novaposhta.js`)).call({}, mod);
const np = mod.exports;

// TEST-NP-009 - verifies REQ-NP-010, REQ-NP-020 (match highlighting is split into text parts, never HTML)
test('novaposhta js: splitMatch splits around the first hit without markup', () => {
  assert.deepEqual(np.splitMatch('Львів', 'киї'), [{ t: 'Львів', hit: false }]);
  assert.deepEqual(np.splitMatch('Київ', 'киї'), [{ t: 'Киї', hit: true }, { t: 'в', hit: false }]);
  assert.deepEqual(np.splitMatch('<b>x</b>', 'b'), [{ t: '<', hit: false }, { t: 'b', hit: true }, { t: '>x</b>', hit: false }]);
  assert.deepEqual(np.splitMatch('abc', ''), [{ t: 'abc', hit: false }]);
});

// TEST-NP-009 - verifies REQ-NP-050 (requests are debounced; URLs are built with encoded parameters)
test('novaposhta js: debounce and url building', async () => {
  let n = 0;
  const f = np.debounce(() => { n++; }, 20);
  f(); f(); f();
  await new Promise((r) => setTimeout(r, 60));
  assert.equal(n, 1);
  assert.equal(np.withParams('index.php?route=a/b', { q: 'Київ &x', page: 2 }), 'index.php?route=a/b&q=%D0%9A%D0%B8%D1%97%D0%B2%20%26x&page=2');
  assert.equal(np.withParams('/x', { a: 1 }), '/x?a=1');
  assert.equal(np.weightLabel('до {n} кг', 30), 'до 30 кг');
  assert.equal(np.weightLabel('до {n} кг', 0), '');
});

// TEST-NP-010 - verifies REQ-NP-012, REQ-NP-013 (the form is active for Ukraine or without a country field)
test('novaposhta js: formActive follows the country', () => {
  const cfg = { only_ua: true, country_id: 220 };
  assert.equal(np.formActive(cfg, '220'), true);
  assert.equal(np.formActive(cfg, '176'), false);
  assert.equal(np.formActive(cfg, null), true);
  assert.equal(np.formActive({ only_ua: false, country_id: 220 }, '176'), true);
  assert.equal(np.formActive({ only_ua: true, country_id: 0 }, '176'), true);
});

// TEST-NP-009 - verifies REQ-NP-051, REQ-NP-023 (no innerHTML; failures leave plain inputs)
test('novaposhta js: renders with textContent only and swallows failures', () => {
  const js = read(`${E}/upload/catalog/view/javascript/ocs/novaposhta.js`);
  assert.ok(!/innerHTML|insertAdjacentHTML|document\.write/.test(js));
  assert.match(js, /\.catch\(function \(\) \{ \/\* the field stays a plain input \*\/ \}\)/);
  assert.match(js, /MutationObserver/);
  assert.match(js, /role', 'listbox'/);
});

// TEST-NP-005 - verifies REQ-NP-005, REQ-NP-055 (cron entry compares the token in constant time and refuses when disabled)
test('novaposhta: cron entry and CLI are guarded', () => {
  const c = read(`${E}/upload/catalog/controller/extension/ocs_novaposhta/cron.php`);
  assert.match(c, /hash_equals\(\$token, \$key\)/);
  assert.match(c, /!\$this->config->get\('shipping_ocs_novaposhta_status'\)/);
  assert.match(c, /HTTP\/1\.1 403 Forbidden/);
  const cli = read(`${E}/upload/system/library/ocs/novaposhta/bin/sync.php`);
  assert.match(cli, /PHP_SAPI !== 'cli'/);
  assert.match(cli, /--dry-run/);
});

// TEST-NP-008 - verifies REQ-NP-052 (the key is write-only in the admin form)
test('novaposhta admin: api key is never printed back and the form validates', () => {
  const t = read(`${E}/upload/admin/view/template/extension/shipping/ocs_novaposhta.twig`);
  assert.match(t, /name="shipping_ocs_novaposhta_api_key" value=""/);
  const a = read(`${E}/upload/admin/controller/extension/shipping/ocs_novaposhta.php`);
  assert.match(a, /\$data\['shipping_ocs_novaposhta_api_key'\] = ''/);
  assert.match(a, /editSetting\('shipping_ocs_novaposhta'/);
  assert.match(a, /hasPermission\('modify', 'extension\/shipping\/ocs_novaposhta'\)/);
});

// TEST-NP-006 - verifies REQ-NP-050, REQ-NP-051 (endpoints are JSON, throttled, switchable, local data only)
test('novaposhta api: json, throttle and kill switch, no outbound calls', () => {
  const a = read(`${E}/upload/catalog/controller/extension/ocs_novaposhta/api.php`);
  assert.match(a, /Content-Type: application\/json/);
  assert.match(a, /HTTP\/1\.1 429/);
  assert.match(a, /shipping_ocs_novaposhta_status/);
  assert.ok(!/curl_|Client\(/.test(a));
  const r = read(`${E}/upload/system/library/ocs/novaposhta/repository.php`);
  assert.ok(!/curl_|Client\(/.test(r));
});

// TEST-NP-011 - verifies REQ-NP-054 (strings are identical across languages)
test('novaposhta: strings are identical across languages', () => {
  const keys = (side, file, l) => [...read(`${E}/upload/${side}/language/${l}/${file}`).matchAll(/\$_\['([a-z_]+)'\]/g)].map((m) => m[1]).sort();
  for (const file of ['extension/shipping/ocs_novaposhta.php', 'extension/ocs_novaposhta/np.php']) {
    for (const l of ['en-gb', 'ru-ru']) assert.deepEqual(keys('catalog', file, l), keys('catalog', file, 'uk-ua'), `${file} ${l}`);
  }
  const adm = 'extension/shipping/ocs_novaposhta.php';
  assert.deepEqual(keys('admin', adm, 'en-gb'), keys('admin', adm, 'uk-ua'));
  // every get('...') key used by the admin controller and the template exists
  const used = new Set([...read(`${E}/upload/admin/controller/extension/shipping/ocs_novaposhta.php`).matchAll(/language->get\('([a-z_]+)'\)/g)].map((m) => m[1]));
  const defined = new Set(keys('admin', adm, 'uk-ua'));
  for (const k of used) assert.ok(defined.has(k) || k === 'text_home' || k === 'datetime_format', `admin string ${k}`);
});

// TEST-NP-001 - verifies REQ-NP-030, REQ-NP-053 (manifest, shipping model contract, no core files touched)
test('novaposhta: manifest and shipping model contract', () => {
  const ext = JSON.parse(read(`${E}/extension.json`));
  assert.equal(ext.code, 'ocs_novaposhta');
  assert.equal(ext.type, 'shipping');
  assert.equal(ext.spec, 'SPEC-085');
  const m = read(`${E}/upload/catalog/model/extension/shipping/ocs_novaposhta.php`);
  assert.match(m, /class ModelExtensionShippingOcsNovaposhta extends Model/);
  assert.match(m, /'code'\s+=> 'ocs_novaposhta\.point'/);
  assert.match(m, /error_point/);
  const mig = read(`${E}/db/001_install.php`);
  assert.match(mig, /register\('shipping', 'ocs_novaposhta'\)/);
  for (const t of ext.tables) assert.ok(mig.includes(`'${t}'`), t);
});

// TEST-NP-007 - verifies REQ-NP-031, REQ-NP-032 (order events write the point, handlers never throw)
test('novaposhta events: order data and point record', () => {
  const e = read(`${E}/upload/catalog/controller/extension/ocs_novaposhta/events.php`);
  assert.match(e, /shipping_city'\] = /);
  assert.match(e, /shipping_address_1'\] = /);
  assert.match(e, /REPLACE INTO `" \. DB_PREFIX \. "ocs_np_order`/);
  assert.equal((e.match(/catch \(\\Exception \$e\)/g) || []).length, 2);
  assert.match(e, /checkout\/\(checkout\|simplecheckout/);
});
