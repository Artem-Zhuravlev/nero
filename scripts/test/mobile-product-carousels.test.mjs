// SPEC-106: product modules are carousels on phones (2 cards per view), grids from 768px.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
const MOD = 'custom/theme/ocs/template/extension/module/';

// TEST-MPK-001 - verifies REQ-MPK-001, REQ-MPK-005
test('product modules print carousel markup, empty list prints nothing', () => {
  for (const n of ['featured', 'latest', 'special', 'bestseller', 'featured_product']) {
    const t = read(MOD + n + '.twig');
    assert.match(t, /class="ocs-carousel ocs-mc"/, n);
    assert.match(t, /\{% if products %\}/, n);
    assert.ok(!t.includes('class="ocs-cards"'), n);
  }
  assert.match(read('custom/extensions/ocs_viewed/upload/catalog/view/theme/default/template/extension/module/ocs_viewed.twig'), /ocs-carousel ocs-mc/);
});

// TEST-MPK-002 - verifies REQ-MPK-002, REQ-MPK-003, REQ-MPK-004
test('css: two cards per view on phones, grid from 768px', () => {
  const css = read('custom/theme/ocs/stylesheet/stylesheet.css');
  assert.match(css, /\.ocs-mc \.ocs-carousel-item[^{]*\{ flex: 0 0 calc\(\(100% - var\(--sp-2\)\) \/ 2\)/);
  assert.match(css, /@media \(min-width: 768px\) \{\s*\.ocs-mc \.ocs-carousel-track \{\s*display: grid/);
});

// TEST-MPK-003 - verifies REQ-MPK-001, REQ-MPK-003
test('js: Swiper shows 2 slides on phones and is skipped for .ocs-mc on wider screens', () => {
  const js = read('custom/theme/ocs/javascript/theme.js');
  assert.match(js, /slidesPerView: single \? 1 : \(twoUp \? 2 : 'auto'\)/);
  assert.match(js, /classList\.contains\('ocs-mc'\) && !phone\.matches/);
  assert.match(js, /swiper\.destroy\(true, true\)/);
});
