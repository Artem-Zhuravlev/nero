// SPEC-099: every product and article block uses the shared cards.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

const THEME = join(REPO_ROOT, 'custom/theme/ocs/template');
const read = (p) => readFileSync(join(THEME, p), 'utf8');

function twig(dir, out = []) {
  for (const name of readdirSync(dir)) {
    const p = join(dir, name);
    statSync(p).isDirectory() ? twig(p, out) : p.endsWith('.twig') && out.push(p);
  }
  return out;
}

// TEST-PBU-001 - verifies REQ-PBU-001, REQ-PBU-003, REQ-PBU-004, REQ-PBU-005
test('no storefront template prints the old card outside ocs/card.twig', () => {
  for (const f of twig(THEME)) {
    if (f.endsWith('card.twig')) continue;
    assert.ok(!/product-thumb|button-group/.test(readFileSync(f, 'utf8')), `${f}: old card markup`);
  }
  const pdp = read('product/product.twig');
  assert.match(pdp, /<div class="ocs-carousel ocs-mc"[^>]*>\s*<div class="ocs-carousel-track">\s*\{% for product in products %\}\s*<div class="ocs-carousel-item">\{% include 'ocs\/template\/ocs\/card\.twig' %\}/);
  assert.match(read('extension/module/featured_article.twig'), /ocs\/blog_card\.twig/);
  assert.match(read('blog/article.twig'), /text_related_product \}\}<\/h2>\s*<div class="ocs-cards">/);
});

// TEST-PBU-002 - verifies REQ-PBU-002: related-card events in the three extensions
test('related product events are registered by migrations', () => {
  for (const [ext, n] of [['ocs_listing', '004'], ['ocs_stickers', '003'], ['ocs_ajax_cart', '003']]) {
    const m = readFileSync(join(REPO_ROOT, `custom/extensions/${ext}/db/${n}_related_events.php`), 'utf8');
    assert.match(m, /catalog\/view\/' \. \$route \. '\/before/);
    assert.match(m, /'product\/product' => 'product', 'blog\/article' => 'article'/);
    assert.match(readFileSync(join(REPO_ROOT, `custom/extensions/${ext}/extension.json`), 'utf8'), new RegExp(`${ext}_related_product`));
  }
});
