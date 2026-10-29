import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

// SPEC-117: cart page quantity stepper (- / +) without the update button.
const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');

// TEST-CART-010 - verifies REQ-CART-010 (no update button, minus / plus around the input)
test('cart page: stepper markup without the update button', () => {
  const t = read('custom/theme/ocs/template/checkout/cart.twig');
  assert.ok(!t.includes('#i-return'), 'update button removed');
  assert.ok(!t.includes('type="submit"'), 'no submit button in the cart form');
  for (const part of ['ocs-step-minus', 'ocs-step-plus', 'ocs-step-input', '#i-minus', '#i-plus', 'cart.remove(']) assert.ok(t.includes(part), part);
});

// TEST-CART-011 - verifies REQ-CART-011 (debounced form submit, minimum 1)
test('cart page: stepper script submits the cart form', () => {
  const j = read('custom/theme/ocs/javascript/theme.js');
  for (const part of ['.ocs-step', 'form.submit()', 'Math.max(1,', 'ocs-step-input']) assert.ok(j.includes(part), part);
});

// TEST-CART-012 - verifies REQ-CART-012 (pill stepper, card rows)
test('cart page: stepper and row styles', () => {
  const c = read('custom/theme/ocs/stylesheet/stylesheet.css');
  for (const part of ['.ocs-stepper {', '.ocs-step:disabled', '.ocs-cart-table > tbody > tr:hover > td']) assert.ok(c.includes(part), part);
});
