import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

// SPEC-080: schema.org JSON-LD for every page type; static checks (the behaviour is covered by StructuredDataTest / StructuredDataEventTest in the PHP runner).
const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
const walk = (d) => readdirSync(d).flatMap((n) => { const p = join(d, n); return statSync(p).isDirectory() ? walk(p) : [p]; });

// TEST-SD-008 - verifies REQ-SD-003 (no microdata left in any template, no JSON-LD built by hand in a template)
test('templates carry no microdata and no hand-made JSON-LD', () => {
  const bad = [];
  for (const root of ['custom/theme', 'custom/extensions']) {
    for (const f of walk(join(REPO_ROOT, root))) {
      if (!f.endsWith('.twig')) continue;
      const s = readFileSync(f, 'utf8');
      if (/\bitemscope\b|\bitemprop=|\bitemtype=|application\/ld\+json/.test(s)) bad.push(f);
    }
  }
  assert.deepEqual(bad, []);
});

// TEST-SD-006 - verifies REQ-SD-001 (one event on every storefront view after, registered by a migration; old product-only schema removed)
test('one view-after event prints the JSON-LD; the old product schema is gone', () => {
  const m = read('custom/db/migrations/0011_structured_data_settings.php');
  assert.ok(m.includes("'catalog/view/*/after', 'extension/ocs/events/structuredData'"));
  const ev = read('custom/extensions/ocs/upload/catalog/controller/extension/ocs/events.php');
  assert.ok(ev.includes('public function structuredData(&$route, &$data, &$output)'));
  assert.equal(ev.split('StructuredData::inject(').length - 1, 1, 'one injection point');
  assert.ok(!read('custom/extensions/ocs_product_extras/upload/system/library/ocs/productextras.php').includes('function schema('));
  assert.ok(!read('custom/extensions/ocs_product_extras/upload/catalog/controller/extension/ocs_product_extras/events.php').includes("'schema'"));
});

// TEST-SD-007 - verifies REQ-SD-009 (admin fields and strings in every admin language)
test('theme settings form has the schema fields and every admin language has their strings', () => {
  const twig = read('custom/extensions/ocs/upload/admin/view/template/extension/theme/ocs.twig');
  for (const k of ['theme_ocs_schema_status', 'theme_ocs_schema_org_name', 'theme_ocs_schema_org_logo']) assert.ok(twig.includes(`name="${k}"`), k);
  for (const l of ['en-gb', 'uk-ua']) {
    const s = read(`custom/extensions/ocs/upload/admin/language/${l}/extension/theme/ocs.php`);
    for (const k of ['text_schema', 'entry_schema_status', 'entry_schema_org_name', 'entry_schema_org_logo', 'help_schema_status', 'help_schema_org_name', 'help_schema_org_logo']) {
      assert.ok(s.includes(`$_['${k}']`), `${l} ${k}`);
    }
  }
  const ctl = read('custom/extensions/ocs/upload/admin/controller/extension/theme/ocs.php');
  assert.ok(ctl.includes("'schema_status' => 1"));
});

// TEST-SD-001 - verifies REQ-SD-010 (the encoder keeps the four HEX flags)
test('the encoder escapes tag, ampersand, apostrophe and quote characters', () => {
  const s = read('custom/extensions/ocs/upload/system/library/ocs/structureddata.php');
  assert.match(s, /JSON_HEX_TAG \| JSON_HEX_AMP \| JSON_HEX_APOS \| JSON_HEX_QUOT/);
});
