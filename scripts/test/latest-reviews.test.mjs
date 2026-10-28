// SPEC-081 latest reviews module checks.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
const E = 'custom/extensions/ocs_latest_reviews';

// TEST-LRV-001 - verifies REQ-LRV-001, REQ-LRV-012 (manifest matches the migration; instances live in the core module table)
test('latest reviews: manifest, migration and admin save into the module table', () => {
  const ext = JSON.parse(read(`${E}/extension.json`));
  assert.equal(ext.code, 'ocs_latest_reviews');
  assert.equal(ext.spec, 'SPEC-081');
  assert.deepEqual(ext.tables, []);
  const mig = read(`${E}/db/001_install.php`);
  assert.ok(mig.includes("register('module', 'ocs_latest_reviews')"));
  assert.ok(mig.includes("grant(1, 'extension/module/ocs_latest_reviews')"));
  const adm = read(`${E}/upload/admin/controller/extension/module/ocs_latest_reviews.php`);
  assert.match(adm, /addModule\('ocs_latest_reviews'/);
  assert.match(adm, /editModule\(/);
});

// TEST-LRV-002 - verifies REQ-LRV-003, REQ-LRV-010 (one JOIN query: approved reviews, visible products, store, language; cached)
test('latest reviews: model query filters and is cached', () => {
  const m = read(`${E}/upload/catalog/model/extension/ocs_latest_reviews/reviews.php`);
  assert.match(m, /r\.status = '1' AND p\.status = '1' AND p\.date_available <= NOW\(\)/);
  assert.match(m, /product_to_store/);
  assert.match(m, /pd\.language_id/);
  assert.match(m, /ORDER BY r\.date_added DESC/);
  assert.match(m, /cache->get/);
  assert.match(m, /cache->set/);
  assert.equal((m.match(/->query\(/g) || []).length, 1);
});

// TEST-LRV-003 - verifies REQ-LRV-004, REQ-LRV-005, REQ-LRV-006, REQ-LRV-011 (empty block renders nothing; values escaped; carousel markup and css exist)
test('latest reviews: controller escapes output, returns empty without reviews, template matches carousel contract', () => {
  const c = read(`${E}/upload/catalog/controller/extension/module/ocs_latest_reviews.php`);
  assert.match(c, /if \(!\$reviews\) \{\s*return '';/);
  assert.match(c, /htmlspecialchars\(\$text/);
  assert.match(c, /htmlspecialchars\(html_entity_decode\(\(string\)\$row\['author'\]/);
  const t = read(`${E}/upload/catalog/view/theme/default/template/extension/module/ocs_latest_reviews.twig`);
  assert.match(t, /ocs-carousel-track/);
  assert.match(t, /ocs-carousel-prev" aria-label="\{\{ text_prev \}\}"/);
  assert.match(t, /ocs-carousel-next" aria-label="\{\{ text_next \}\}"/);
  assert.match(t, /rating\.twig/);
  assert.ok(read('custom/theme/ocs/stylesheet/stylesheet.css').includes('.ocs-lreview'));
});

// TEST-LRV-004 - verifies REQ-LRV-002 (admin validates the ranges of limit, length and rating)
test('latest reviews: admin validates setting ranges', () => {
  const a = read(`${E}/upload/admin/controller/extension/module/ocs_latest_reviews.php`);
  assert.match(a, /'limit' => array\(1, 20\)/);
  assert.match(a, /'length' => array\(40, 1000\)/);
  assert.match(a, /'min_rating' => array\(1, 5\)/);
});

// TEST-LRV-005 - verifies REQ-LRV-013 (language files have identical keys in every language)
test('latest reviews: strings are identical across languages', () => {
  const keys = (side, l) => [...read(`${E}/upload/${side}/language/${l}/extension/module/ocs_latest_reviews.php`).matchAll(/\$_\['([a-z_]+)'\]/g)].map((m) => m[1]).sort();
  for (const l of ['en-gb', 'ru-ru']) assert.deepEqual(keys('catalog', l), keys('catalog', 'uk-ua'));
  assert.deepEqual(keys('admin', 'en-gb'), keys('admin', 'uk-ua'));
});
