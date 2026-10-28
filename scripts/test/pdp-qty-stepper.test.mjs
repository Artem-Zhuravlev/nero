// SPEC-101: quantity stepper on the product page.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
const js = read('custom/theme/ocs/javascript/theme.js');
const twig = read('custom/theme/ocs/template/product/product.twig');
const css = read('custom/theme/ocs/stylesheet/stylesheet.css');

// the pure function is loaded straight from theme.js
const m = js.match(/function clamp\(value, min, max\) \{[\s\S]*?\n\t\}/);
const clamp = new Function(`${m[0]}; return clamp;`)();

// TEST-QTY-001 - verifies REQ-QTY-002, REQ-QTY-003, REQ-QTY-004, REQ-QTY-005
test('clamp keeps the quantity a multiple of the minimum within stock', () => {
  assert.equal(clamp('0', 1, 0), 1);
  assert.equal(clamp('abc', 3, 0), 3);
  assert.equal(clamp('7', 3, 0), 6);
  assert.equal(clamp('100', 3, 9), 9);
  assert.equal(clamp('100', 1, 0), 100);
  assert.equal(clamp('5', 2, 1), 2);
});

// TEST-QTY-002 - verifies REQ-QTY-001, REQ-QTY-006
test('markup and styles of the stepper', () => {
  assert.match(twig, /data-ocs-qty="1"[^>]*data-min=[^>]*data-max=/);
  assert.match(twig, /data-ocs-qty-dec aria-label=/);
  assert.match(twig, /data-ocs-qty-inc aria-label=/);
  assert.match(twig, /name="quantity"[^>]*inputmode="numeric"/);
  assert.match(css, /\.ocs-qty \.form-control \{[^}]*inline-size: 3\.5rem/);
});
