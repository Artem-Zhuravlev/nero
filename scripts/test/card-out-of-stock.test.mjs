import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

// SPEC-077: out-of-stock cards.
const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');

// TEST-OOS-001 - verifies REQ-OOS-001..003 (header event, migration, extension manifest)
test('out-of-stock: header event hands the form URL to the cards', () => {
  const ev = read('custom/extensions/ocs_back_in_stock/upload/catalog/controller/extension/ocs_back_in_stock/events.php');
  assert.ok(ev.includes('function beforeHeader') && ev.includes("$data['ocs_notify']"));
  assert.ok(read('custom/extensions/ocs_back_in_stock/db/004_header_event.php').includes('catalog/view/common/header/before'));
  assert.ok(read('custom/extensions/ocs_back_in_stock/extension.json').includes('ocs_back_in_stock_header_before'));
  assert.ok(read('custom/theme/ocs/template/common/header.twig').includes('data-ocs-notify='));
});

// TEST-OOS-002 - verifies REQ-OOS-001, 003, 007
test('out-of-stock: card swaps the cart button for the bell', () => {
  const t = read('custom/theme/ocs/template/ocs/card.twig');
  for (const hook of ['is-out', 'ocs-notify-btn', '#i-bell', "state == 'out'"]) assert.ok(t.includes(hook), hook);
  assert.ok(read('custom/theme/ocs/template/ocs/sprite.twig').includes('id="i-bell"'));
});

// TEST-OOS-003 - verifies REQ-OOS-004..006
test('out-of-stock: grey image and 18px price', () => {
  const c = read('custom/theme/ocs/stylesheet/stylesheet.css');
  assert.ok(c.includes('.product-thumb.is-out > .image .ocs-card-img img { filter: grayscale(.8); }'));
  assert.ok(!/\.ocs-buy-row \.price \{ font-size: clamp/.test(c));
  assert.ok(c.includes('.ocs-buy-row .price { font-size: 1.125rem;'));
});
