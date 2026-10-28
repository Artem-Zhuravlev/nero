// SPEC-084 blog authors, dates, multilingual settings, comments and reactions: structure checks.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
const E = 'custom/extensions/ocs_blog';
const T = 'custom/theme/ocs/template';

// TEST-BLG-001 - verifies REQ-BLG-001, REQ-BLG-060 (manifest, tables, permission, events registered by migrations)
test('blog: manifest events and tables match the migrations', () => {
  const ext = JSON.parse(read(`${E}/extension.json`));
  assert.equal(ext.code, 'ocs_blog');
  assert.equal(ext.spec, 'SPEC-084');
  const mig = ['001_install', '002_settings', '003_storefront'].map((f) => read(`${E}/db/${f}.php`)).join('\n');
  for (const t of ['ocs_blog_author', 'ocs_blog_author_description', 'ocs_blog_article', 'ocs_blog_comment', 'ocs_blog_vote']) {
    assert.ok(mig.includes(`createTable('${t}'`), t);
    assert.ok(ext.tables.includes(`oc_${t}`), t);
  }
  assert.ok(mig.includes("grant(1, 'extension/ocs_blog/author')"));
  for (const ev of ext.events) assert.ok(mig.includes(`'${ev}'`), `event ${ev} is registered`);
  assert.ok(!/ALTER TABLE/i.test(mig), 'core tables are not altered');
});

// TEST-BLG-003 - verifies REQ-BLG-003, REQ-BLG-010 (article form OCMOD + save handlers incl. date_available the core never writes)
test('blog: article form extras are saved by events', () => {
  const x = read('custom/ocmod/ocs_blog_article_form.ocmod.xml');
  assert.match(x, /ocs_blog_article_extra/);
  assert.match(x, /admin\/view\/template\/blog\/article_form\.twig/);
  const ev = read(`${E}/upload/admin/controller/extension/ocs_blog/events.php`);
  assert.match(ev, /afterAddArticle/);
  assert.match(ev, /afterEditArticle/);
  assert.match(read(`${E}/upload/admin/model/extension/ocs_blog/author.php`), /UPDATE `" \. DB_PREFIX \. "article` SET date_available/);
});

// TEST-BLG-004 - verifies REQ-BLG-020, REQ-BLG-021, REQ-BLG-022 (language fields in admin, JSON setting, storefront localisation)
test('blog: settings are multilingual in admin and storefront', () => {
  const x = read('custom/ocmod/ocs_blog_setting.ocmod.xml');
  assert.match(x, /ocs_blog_lang_fields/);
  const adm = read(`${E}/upload/admin/controller/extension/ocs_blog/events.php`);
  assert.match(adm, /configblog_description/);
  assert.match(adm, /beforeEditSetting/);
  const st = read(`${E}/upload/catalog/controller/extension/ocs_blog/events.php`);
  assert.match(st, /langSettings/);
  const mig = read(`${E}/db/002_settings.php`);
  for (const r of ['blog/latest', 'blog/category', 'blog/article', 'blog/menu']) assert.ok(mig.includes(`catalog/controller/${r}/before`), r);
});

// TEST-BLG-005 - verifies REQ-BLG-004, REQ-BLG-005 (author block in the article template, JSON-LD author)
test('blog: article page shows the author and JSON-LD carries it', () => {
  const t = read(`${T}/blog/article.twig`);
  assert.match(t, /class="ocs-author"/);
  assert.match(t, /rel="noopener nofollow me"/);
  assert.ok(!t.includes('@generated'));
  assert.match(read('custom/extensions/ocs/upload/catalog/controller/extension/ocs/events.php'), /getAuthors/);
  assert.match(read('custom/extensions/ocs/upload/system/library/ocs/structureddata.php'), /'person'/);
});

// TEST-BLG-006 - verifies REQ-BLG-033, REQ-BLG-061 (votes: POST only, same host, rate limit, recomputed counters)
test('blog: vote endpoint is guarded', () => {
  const c = read(`${E}/upload/catalog/controller/extension/ocs_blog/vote.php`);
  assert.match(c, /REQUEST_METHOD'\] != 'POST'/);
  assert.match(c, /sameHost/);
  assert.match(c, /recentVotes/);
  const m = read(`${E}/upload/catalog/model/extension/ocs_blog/blog.php`);
  assert.match(m, /SUM\(value = 1\)/);
  assert.match(m, /REPLACE INTO/);
});

// TEST-BLG-007 - verifies REQ-BLG-030, REQ-BLG-032 (popup form with stars, no pros/cons, comment cards with reactions)
test('blog: comments use a popup form and cards', () => {
  const t = read(`${T}/blog/article.twig`);
  assert.match(t, /data-ocs-popup="ocs-review-box"/);
  assert.match(t, /name="rating"/);
  assert.ok(!/name="pros"|name="cons"/.test(t));
  const r = read(`${T}/blog/review.twig`);
  assert.match(r, /ocs-comment-list/);
  assert.match(r, /blog_react\.twig/);
});

// TEST-BLG-008 - verifies REQ-BLG-050 (all blog lists and modules share one card)
test('blog: lists and modules include the shared card', () => {
  for (const f of ['blog/latest.twig', 'blog/category.twig', 'extension/module/blog_latest.twig', 'extension/module/blog_featured.twig', 'blog/article.twig']) {
    assert.match(read(`${T}/${f}`), /ocs\/blog_card\.twig/, f);
  }
  assert.ok(existsSync(join(REPO_ROOT, `${T}/ocs/blog_card.twig`)));
  const css = read('custom/theme/ocs/stylesheet/stylesheet.css');
  assert.match(css, /\.ocs-bcard\b/);
  assert.match(css, /\.ocs-react-btn\b/);
});

// TEST-BLG-009 - verifies REQ-BLG-063 (strings in every language, same keys)
test('blog: language files have the same keys', () => {
  const keys = (p) => [...read(p).matchAll(/\$_\['(\w+)'\]/g)].map((m) => m[1]).sort();
  const cat = ['uk-ua', 'ru-ru', 'en-gb'].map((l) => keys(`${E}/upload/catalog/language/${l}/extension/ocs_blog/blog.php`));
  assert.deepEqual(cat[0], cat[1]);
  assert.deepEqual(cat[0], cat[2]);
  const adm = ['uk-ua', 'en-gb'].map((l) => keys(`${E}/upload/admin/language/${l}/extension/ocs_blog/author.php`));
  assert.deepEqual(adm[0], adm[1]);
});
