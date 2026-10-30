import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

// SPEC-045: heading rules checked statically on the storefront templates.
const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
const walk = (dir) => readdirSync(dir).flatMap((n) => {
  const p = join(dir, n);
  return statSync(p).isDirectory() ? walk(p) : p.endsWith('.twig') ? [p] : [];
});
const templates = () => [
  ...walk(join(REPO_ROOT, 'custom/theme/ocs/template')),
  ...readdirSync(join(REPO_ROOT, 'custom/extensions')).flatMap((e) => {
    const d = join(REPO_ROOT, 'custom/extensions', e, 'upload/catalog/view');
    try { return walk(d); } catch { return []; }
  }),
];
const headings = (s) => [...s.matchAll(/<h([1-6])\b[^>]*>([\s\S]*?)<\/h\1>/g)].map((m) => ({ level: +m[1], text: m[2] }));

// TEST-SEO-001 - verifies REQ-SEO-001 (no price, stock, count or button caption inside a heading)
test('no heading holds a price or another non-title value', () => {
  const bad = [];
  for (const f of templates()) {
    for (const h of headings(readFileSync(f, 'utf8'))) {
      if (/\{\{\s*(price|special|tax|points|product\.(price|special)|total|total_text|stock)\b/.test(h.text)) bad.push(`${f}: ${h.text.trim().slice(0, 40)}`);
    }
  }
  assert.deepEqual(bad, []);
  assert.ok(/<p class="ocs-price"><span data-ocs-cur>\{\{ price \}\}<\/span><\/p>/.test(read('custom/theme/ocs/template/product/product.twig')));
});

// TEST-SEO-002 - verifies REQ-SEO-002 (product titles are h4 with the link inside, everywhere)
test('product titles are <h4> in every product list', () => {
  const bad = [];
  let seen = 0;
  for (const f of templates()) {
    for (const h of headings(readFileSync(f, 'utf8'))) {
      if (/\{\{\s*product\.name\s*\}\}/.test(h.text)) {
        seen++;
        if (h.level !== 4 || !/<a href="\{\{ product\.href \}\}">/.test(h.text)) bad.push(`${f}: h${h.level}`);
      }
    }
  }
  assert.ok(seen >= 8, `${seen} product title headings found`);
  assert.deepEqual(bad, []);
});

// TEST-SEO-003 - verifies REQ-SEO-003 (module / section titles are h2; no level skipped outside product titles)
test('section titles are h2 and the product and category outline has no skipped level', () => {
  for (const m of ['featured', 'latest', 'bestseller', 'special', 'featured_product']) {
    const h = headings(read(`custom/theme/ocs/template/extension/module/${m}.twig`)).find((x) => /heading_title/.test(x.text));
    assert.equal(h.level, 2, m);
  }
  for (const rel of ['custom/theme/ocs/template/product/product.twig', 'custom/theme/ocs/template/product/category.twig']) {
    const hs = headings(read(rel)).filter((h) => !/product\.name/.test(h.text));
    assert.equal(hs.filter((h) => h.level === 1).length, 1, `${rel}: one h1`);
    let prev = 0;
    for (const h of hs) {
      assert.ok(h.level <= prev + 1, `${rel}: h${h.level} after h${prev} ("${h.text.trim().slice(0, 30)}")`);
      prev = h.level;
    }
  }
});

// TEST-SEO-004 - verifies REQ-SEO-004 (no empty and no nested headings)
test('headings are never empty or nested', () => {
  const bad = [];
  for (const f of templates()) {
    const s = readFileSync(f, 'utf8');
    for (const h of headings(s)) {
      if (!h.text.replace(/<[^>]*>/g, '').replace(/\{[{%#][\s\S]*?[}%#]\}/g, 'x').trim()) bad.push(`${f}: empty`);
      if (/<h[1-6]\b/.test(h.text)) bad.push(`${f}: nested`);
    }
  }
  assert.deepEqual(bad, []);
});

// TEST-PDP-001 - verifies REQ-PDP-001, REQ-PDP-002 (info column: no lists, definition block, hooks kept)
test('product info column has no list and keeps the core hooks', () => {
  const s = read('custom/theme/ocs/template/product/product.twig');
  const info = s.slice(s.indexOf('<div class="ocs-pdp-head">'), s.indexOf('<div id="product"'));
  assert.ok(info.length > 500 && !/<ul|<li/.test(info), 'no ul/li in the info column');
  for (const hook of ['<dl class="ocs-facts">', 'class="ocs-key-attrs"', 'ocs-price-box', "wishlist.add('{{ product_id }}')", "compare.add('{{ product_id }}')", 'ocs_show_sku', 'ocs_show_stock']) assert.ok(info.includes(hook), hook);
  for (const hook of ['id="product"', 'id="button-cart"', 'id="input-quantity"', 'id="ocs-buy-slot"']) assert.ok(s.includes(hook), hook);
  assert.ok(read('custom/theme/ocs/stylesheet/stylesheet.css').includes('.ocs-facts'));
});

// TEST-SEO-005 - verifies REQ-SEO-001 (the home page has exactly one h1, hidden visually, from the store title)
test('home page carries one visually hidden h1', () => {
  assert.equal((read('custom/theme/ocs/template/common/home.twig').match(/<h1\b/g) || []).length, 1);
  assert.ok(read('custom/extensions/ocs/upload/catalog/controller/extension/ocs/events.php').includes("$data['ocs_h1'] = $this->config->get('config_meta_title')"));
});
