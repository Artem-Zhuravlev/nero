// SPEC-110: product block titles and card names are divs, not h1-h5.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
const mod = 'custom/theme/ocs/template/extension/module/';
const ext = (n) => `custom/extensions/${n}/upload/catalog/view/theme/default/template/extension/module/${n}.twig`;
const files = [
  ...['bestseller', 'featured', 'featured_product', 'latest', 'special', 'blog_featured', 'blog_latest', 'featured_article'].map((n) => `${mod}${n}.twig`),
  ext('ocs_viewed'), ext('ocs_latest_reviews'), ext('ocs_product_carousel'), 'custom/theme/ocs/template/ocs/card.twig',
];

// TEST-PBH-001 - verifies REQ-PBH-001, REQ-PBH-002
test('product blocks use div titles and the card name class', () => {
  for (const f of files) assert.doesNotMatch(read(f), /<h[1-5]\b/, f);
  assert.match(read('custom/theme/ocs/template/ocs/card.twig'), /<div class="ocs-card-name">/);
  const css = read('custom/theme/ocs/stylesheet/stylesheet.css');
  assert.doesNotMatch(css, /caption h4/);
  assert.match(css, /\.ocs-modcards > \.ocs-sec-title[^{]*\{ margin: 0 0 var\(--sp-4\)/);
});
