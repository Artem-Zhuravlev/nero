// SPEC-124: spec list, stock icons, product code in the card, no bold outside prices/headings.
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const read = (p) => fs.readFileSync(new URL(`../../${p}`, import.meta.url), 'utf8');
const pdp = read('custom/theme/ocs/template/product/product.twig');
const card = read('custom/theme/ocs/template/ocs/card.twig');
const sprite = read('custom/theme/ocs/template/ocs/sprite.twig');
const css = read('custom/theme/ocs/stylesheet/stylesheet.css');

test('TEST-PDP-001 — verifies REQ-PDP-001: attributes are a striped list, not a bordered table', () => {
  assert.ok(pdp.includes('class="ocs-specs"') && pdp.includes('ocs-specs-row'));
  const sec = pdp.slice(pdp.indexOf('id="tab-specification"'), pdp.indexOf('id="tab-review"'));
  assert.ok(!sec.includes('<table') && !sec.includes('<strong>'));
  assert.match(css, /\.ocs-specs-row:nth-of-type\(odd\)/);
});

test('TEST-STK-001 — verifies REQ-STK-001, REQ-STK-002: check / thin cross next to the stock label', () => {
  for (const [name, src] of [['card', card], ['product', pdp]]) {
    assert.ok(src.includes('i-x-thin') && src.includes('i-check'), name);
    assert.ok(!src.includes('ocs-dot'), name);
  }
  assert.ok(sprite.includes('id="i-x-thin"') && sprite.includes('id="i-check"'));
});

test('TEST-CODE-001 — verifies REQ-CODE-001: code is a static block in the caption above the name', () => {
  const imageEnd = card.indexOf('<div class="caption">');
  assert.ok(!card.slice(0, imageEnd).includes('ocs-code'));
  assert.ok(card.indexOf('class="ocs-code"') > imageEnd && card.indexOf('class="ocs-code"') < card.indexOf('class="ocs-card-name"'));
  assert.ok(!/\.ocs-code\s*\{[^}]*position:\s*absolute/.test(css));
  assert.ok(!/\.product-thumb > \.image \.ocs-code/.test(css));
});

test('TEST-TYPO-001 — verifies REQ-TYPO-001, REQ-TYPO-002: weights >= 600 only on prices and headings', () => {
  const keep = /price|\.ocs-now|ocs-sticky-price|ocs-sec-title|ocs-block-title|(^|[\s,>])h[1-6]\b|legend|ocs-lb-price|ocs-cart-totals|ocs-sum-row|ocs-acc-title|ocs-cc-h\b|ocs-home-gtitle|ocs-stars-avg|ocs-bcard-title|ocs-info-title|ocs-author-name|ocs-article-head|ocs-cmp-name|ocs-savings|ocs-facts dd|ocs-np-title/;
  const bad = [];
  for (const m of css.matchAll(/font-weight:\s*([6-9]00)/g)) {
    const open = css.lastIndexOf('{', m.index);
    const prev = Math.max(css.lastIndexOf('}', open), css.lastIndexOf(';', open), css.lastIndexOf('/', open));
    const sel = css.slice(prev + 1, open).trim();
    if (!sel.startsWith('@font-face') && !keep.test(sel)) bad.push(sel);
  }
  assert.deepEqual(bad, []);
  assert.match(css, /strong, b, th, label \{ font-weight: 400/);
});
