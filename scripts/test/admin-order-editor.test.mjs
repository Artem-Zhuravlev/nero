import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import vm from 'node:vm';
import { REPO_ROOT } from './helpers.mjs';

// SPEC-122: single-page admin order editor (static checks; the flow itself was driven against a mock catalog API in a browser).
const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
const base = 'custom/extensions/ocs_order_editor';
const up = `${base}/upload/admin`;
const js = read(`${up}/view/javascript/ocs_order_editor/order_editor.js`);

// TEST-ORD-001 — verifies REQ-ORD-021
test('the stock form is replaced by a view event only; the migration registers it and a kill switch', () => {
  const m = read(`${base}/db/001_install.php`);
  assert.ok(m.includes("'admin/view/sale/order_form/before'") && m.includes('extension/ocs_order_editor/events/beforeOrderForm'));
  assert.ok(m.includes('ocs_order_editor_status'));
  const ev = read(`${up}/controller/extension/ocs_order_editor/events.php`);
  assert.ok(ev.includes("$route = 'extension/ocs_order_editor/order_form'") && ev.includes("config->get('ocs_order_editor_status')"));
  assert.ok(ev.includes('catch (\\Exception $e)'), 'handler must never throw to the admin user');
  const ext = JSON.parse(read(`${base}/extension.json`));
  assert.equal(ext.spec, 'SPEC-122');
});

// TEST-ORD-002 — verifies REQ-ORD-001, REQ-ORD-020, REQ-ORD-023
test('the template is one page with no wizard tabs and loads its own assets', () => {
  const t = read(`${up}/view/template/extension/ocs_order_editor/order_form.twig`);
  assert.ok(t.includes('id="oe-root"') && t.includes('order_editor.js') && t.includes('order_editor.css'));
  assert.ok(!t.includes('nav-tabs') && !t.includes('data-toggle="tab"'));
  const css = read(`${up}/view/stylesheet/ocs_order_editor/order_editor.css`);
  assert.ok(css.includes('.oe-mbar') && css.includes('position: sticky') && css.includes('@media (max-width: 767px)'));
  for (const s of ['oe-card-customer', 'oe-card-products', 'oe-card-delivery', 'oe-card-payment', 'oe-card-summary']) assert.ok(js.includes(s), s);
});

// TEST-ORD-003 — verifies REQ-ORD-002, REQ-ORD-022
test('the script parses and replays every API stage through one serial queue', () => {
  assert.doesNotThrow(() => new vm.Script(js, { filename: 'order_editor.js' }));
  for (const r of ['api/currency', 'api/customer', 'api/cart/add', 'api/cart/edit', 'api/cart/remove', 'api/cart/products', 'api/payment/address', 'api/shipping/address', 'api/shipping/methods', 'api/payment/methods', 'api/shipping/method', 'api/payment/method', 'api/coupon', 'api/reward', 'api/voucher/add', 'api/order/add', 'api/order/edit']) {
    assert.ok(js.includes(`'${r}'`), r);
  }
  assert.ok(js.includes('chain = run') && js.includes('function enqueue'));
});

// TEST-ORD-004 — verifies REQ-ORD-003, REQ-ORD-012
test('the payer address is a copy of the delivery address unless unchecked; custom fields go in both shapes', () => {
  assert.ok(js.includes("state.same ? ship : addrData('pay')"));
  assert.ok(js.includes('id="oe-same" checked="checked"'));
  assert.ok(js.includes("'custom_field[' + loc + '][' + c.id + ']'") && js.includes("'custom_field[' + c.id + ']'"));
});

// TEST-ORD-006 — verifies REQ-ORD-006
test('methods are chosen by default when one is available or the previous one is still offered', () => {
  assert.ok(js.includes('codes.length === 1') && js.includes('pcodes.length === 1'));
  assert.ok(js.includes('state.shipLost') && js.includes('state.payLost'));
});

// TEST-ORD-007 — verifies REQ-ORD-008, REQ-ORD-009, REQ-ORD-010, REQ-ORD-011
test('save validates first, runs once, and edit mode rebuilds the API session from the order', () => {
  assert.ok(js.includes('if (state.saving)') && js.includes('state.redirecting'));
  assert.ok(js.includes("api(edit ? 'api/order/edit' : 'api/order/add'"));
  assert.ok(js.includes('state.reloadCart = true') && js.includes('markAll()'));
  assert.ok(js.includes('oe_confirm_store'));
});

// TEST-ORD-008 — verifies REQ-ORD-001
test('every string the script and the event use exists in both admin languages', () => {
  const used = new Set([...js.matchAll(/T\('(oe_[a-z_0-9]+)'/g)].map((m) => m[1]));
  ['oe_saved_new', 'oe_saved_edit', 'oe_cancel'].forEach((k) => used.add(k));
  for (const lang of ['uk-ua', 'en-gb']) {
    const src = read(`${up}/language/${lang}/extension/ocs_order_editor/editor.php`);
    for (const key of used) assert.ok(src.includes(`$_['${key}']`), `${lang} lacks ${key}`);
  }
});
