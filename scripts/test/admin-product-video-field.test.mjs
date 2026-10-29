import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

// SPEC-115: no Accessories tab in the admin product form, the video field sits on the Data tab under the model.
const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
const base = 'custom/extensions/ocs_product_extras/upload/admin';

// TEST-PRD-120 — verifies REQ-PRD-120, REQ-PRD-122
test('ocmod puts the video field before the SKU group and adds no tab', () => {
  const x = read('custom/ocmod/ocs_product_extras_form.ocmod.xml');
  assert.ok(x.includes('{{ ocs_video_field }}') && x.includes('for="input-sku"'));
  assert.ok(!x.includes('ocs_extras_tab') && !x.includes('tab-design'));
  const tpl = read(`${base}/view/template/extension/ocs_product_extras/tab.twig`);
  assert.ok(tpl.includes('name="ocs_video"') && tpl.includes('ocs_extras_present') && !tpl.includes('ocs_accessory_ids'));
});

// TEST-PRD-121 — verifies REQ-PRD-121, REQ-PRD-123
test('admin code no longer handles accessory links, but deleting a product still clears them', () => {
  const ev = read(`${base}/controller/extension/ocs_product_extras/events.php`);
  assert.ok(!/accessor/i.test(ev) && ev.includes('setVideo('));
  const model = read(`${base}/model/extension/ocs_product_extras/extras.php`);
  assert.ok(!model.includes('setAccessories') && model.includes('DELETE FROM `" . DB_PREFIX . "ocs_product_accessory`'));
});
