import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

// SPEC-120: out-of-stock bell and out-of-stock-last ordering.
const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');

// TEST-OOS2-002 - verifies REQ-OOS2-001, REQ-OOS2-002 (bell is wired to the notify form, hidden only when the module is off)
test('oos: bell opens the notify form and hides only without the module', () => {
  const t = read('custom/theme/ocs/template/ocs/card.twig');
  assert.match(t, /\{% if ocs_out %\}\s*<button[^>]*ocs-notify-btn ocs-req-btn/);
  const js = read('custom/theme/ocs/javascript/theme.js');
  assert.ok(js.includes("buttons[i].hidden = !document.body.hasAttribute('data-ocs-notify')"));
  assert.ok(js.includes("classList.contains('ocs-req-btn')"));
});

// TEST-OOS2-003 - verifies REQ-OOS2-004, REQ-OOS2-005, REQ-OOS2-006 (every OCMOD anchor exists in core; all 7 ORDER BY lines covered)
test('oos: OCMOD prefixes every ORDER BY of getProducts/getProductSpecials', () => {
  const core = read('core/upload/catalog/model/catalog/product.php');
  const ocmod = read('custom/ocmod/ocs_listing_stock_last.ocmod.xml');
  assert.match(ocmod, /@core-file catalog\/model\/catalog\/product\.php/);
  const anchors = [...ocmod.matchAll(/<search><!\[CDATA\[([\s\S]*?)\]\]><\/search>/g)].map((m) => m[1]);
  assert.equal(anchors.length, 4);
  let covered = 0;
  for (const a of anchors) {
    const n = core.split(a).length - 1;
    assert.ok(n >= 1, `anchor missing in core: ${a}`);
    covered += n;
  }
  const total = (core.match(/\$sql \.= " ORDER BY /g) || []).length;
  assert.equal(covered, total, 'every core ORDER BY must be covered');
  assert.equal((ocmod.match(/\(p\.quantity <= 0\) ASC/g) || []).length, 4);
});

// TEST-OOS2-001 - verifies REQ-OOS2-003 (migration file present and installs the OCMOD)
test('oos: migration enables the module and installs the OCMOD', () => {
  const mig = read('custom/db/migrations/0013_oos_notify_and_last.php');
  assert.ok(mig.includes("'module_ocs_back_in_stock_status', 1"));
  assert.ok(mig.includes('ocs_listing_stock_last.ocmod.xml'));
});
