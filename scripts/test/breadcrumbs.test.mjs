import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

// SPEC-054: no self-link, home as a word. SPEC-080 REQ-SD-003: the schema.org BreadcrumbList is JSON-LD (structured-data.test.mjs), the HTML carries no microdata.
const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
const walk = (d) => readdirSync(d).flatMap((n) => { const p = join(d, n); return statSync(p).isDirectory() ? walk(p) : [p]; });

function storefrontTemplates() {
  const out = [];
  for (const root of ['custom/theme', 'custom/extensions']) {
    for (const f of walk(join(REPO_ROOT, root))) {
      if (f.endsWith('.twig') && !/[\\/]admin[\\/]/.test(f) && read(f.slice(REPO_ROOT.length + 1)).includes('class="breadcrumb"')) out.push(f);
    }
  }
  return out;
}

// TEST-BRC-001 - verifies REQ-BRC-002, REQ-BRC-004, REQ-SD-003
test('every storefront breadcrumb uses the same plain block without microdata and without a self-link', () => {
  const files = storefrontTemplates();
  assert.ok(files.length >= 44, 'found ' + files.length);
  const blocks = new Set();
  for (const f of files) {
    const s = readFileSync(f, 'utf8');
    const m = s.match(/<ul class="breadcrumb"[^>]*>[\s\S]*?<\/ul>/);
    assert.ok(m, f);
    assert.ok(!/itemscope|itemprop|itemtype/.test(m[0]), f);
    assert.ok(m[0].includes('{% if loop.last %}<span aria-current="page">{{ breadcrumb.text }}</span>{% else %}<a href="{{ breadcrumb.href }}">'), f);
    assert.ok(!m[0].includes('#i-home') && !m[0].includes('fa-home'), f);
    blocks.add(m[0].replace(/\s+/g, ' '));
  }
  assert.equal(blocks.size, 1, 'one identical block');
});

// TEST-BRC-002 - verifies REQ-BRC-003
test('home breadcrumb word exists in every language and the event replaces fa-home', () => {
  for (const [l, w] of [['ru-ru', 'Главная'], ['uk-ua', 'Головна'], ['en-gb', 'Home']]) {
    assert.ok(read(`custom/extensions/ocs_shell/upload/catalog/language/${l}/extension/ocs_shell/shell.php`).includes(`$_['ocs_breadcrumb_home'] = '${w}';`), l);
  }
  const ev = read('custom/extensions/ocs_shell/upload/catalog/controller/extension/ocs_shell/events.php');
  assert.ok(ev.includes('function beforeView') && ev.includes("'fa-home'"));
  assert.ok(read('custom/extensions/ocs_shell/db/002_breadcrumb_home.php').includes("'catalog/view/*/before', 'extension/ocs_shell/events/beforeView'"));
});

// TEST-BRC-003 - verifies REQ-BRC-005
test('stylesheet styles the current breadcrumb item', () => {
  assert.ok(read('custom/theme/ocs/stylesheet/stylesheet.css').includes('.breadcrumb > li > [aria-current]'));
});
