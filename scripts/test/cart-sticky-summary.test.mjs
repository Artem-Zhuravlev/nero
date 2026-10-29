import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

// SPEC-118: cart totals in a sticky right column, accordions below them with icons.
const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
const cart = read('custom/theme/ocs/template/checkout/cart.twig');
const css = read('custom/theme/ocs/stylesheet/stylesheet.css');

// TEST-CART-020 - verifies REQ-CART-020, REQ-CART-022 (two columns; totals, then accordions in the side column)
test('cart page: totals and accordions live in the side column', () => {
  for (const part of ['ocs-cart-layout', 'ocs-cart-main', 'ocs-cart-side']) assert.ok(cart.includes(part), part);
  const side = cart.slice(cart.indexOf('<aside'));
  assert.ok(side.indexOf('ocs-cart-sum') < side.indexOf('ocs-cart-extras'), 'totals before accordions');
  assert.ok(side.includes('text_next'), 'heading stays above the accordions');
});

// TEST-CART-021 - verifies REQ-CART-021, REQ-CART-024 (sticky on desktop only)
test('cart page: side column is sticky from 992px', () => {
  const i = css.indexOf('@media (min-width: 992px) {\n\t\t.ocs-cart-layout');
  const m = i >= 0 && /\.ocs-cart-side \{[^\n]*position: sticky/.test(css.slice(i, i + 600));
  assert.ok(m, 'sticky inside the desktop media query');
  assert.ok(css.includes('.ocs-cart-layout { display: grid;'), 'single column grid by default');
});

// TEST-CART-022 - verifies REQ-CART-023 (icons in the accordion headings)
test('cart accordions carry themed icons', () => {
  const want = { coupon: 'i-tag', shipping: 'i-truck', voucher: 'i-gift', reward: 'i-star' };
  for (const [n, icon] of Object.entries(want)) {
    const t = read(`custom/theme/ocs/template/extension/total/${n}.twig`);
    assert.ok(t.includes(`ocs-acc-i" aria-hidden="true"><use href="#${icon}"/>`), `${n} icon`);
    assert.ok(t.includes('#i-chevron-down'), `${n} chevron kept`);
  }
});
