// SPEC-040: installable theme package.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { execFileSync } from 'node:child_process';
import { REPO_ROOT } from './helpers.mjs';
import { collectPackage, disallowedPaths, scanPackage, zip, crc32, packTheme } from '../lib/pack-theme.mjs';

// TEST-PKG-001 - verifies REQ-PKG-001, REQ-PKG-003 (theme and modules land in their usual paths)
test('package contains theme, modules, setup library and payload', () => {
  const files = collectPackage({ root: REPO_ROOT });
  const has = (p) => files.has(p);
  assert.ok(has('upload/catalog/view/theme/ocs/stylesheet/stylesheet.css'));
  assert.ok(has('upload/admin/controller/extension/ocs/setup.php'));
  assert.ok(has('upload/system/library/ocs/setup/autoload.php'));
  assert.ok(has('upload/system/library/ocs/setup/Setup/Service.php'));
  assert.ok(has('upload/system/library/ocs/setup/payload/custom/db/migrations/0001_theme_ocs.php'));
  assert.ok(has('upload/system/library/ocs/setup/payload/custom/extensions/ocs_stickers/db/001_install.php'));
  assert.ok(has('upload/system/library/ocs/setup/payload/custom/ocmod/ocs_listing_sort.ocmod.xml'));
  assert.ok(has('README-INSTALL.md'));
  assert.ok(has('ocmod-xml/ocs_product_extras_form.ocmod.xml')); // TEST-OCM-003 - verifies REQ-OCM-006
  assert.ok(!has('upload/catalog/view/theme/ocs/DESIGN.md'));
  assert.equal(readFileSync(join(REPO_ROOT, 'custom/VERSION'), 'utf8').trim(), files.get('upload/system/library/ocs/setup/payload/VERSION').toString().trim());
});

// TEST-PKG-002 - verifies REQ-PKG-002 (only paths the core installer accepts; core/ is never packed)
test('every upload path is accepted by the core installer allow-list', () => {
  const files = collectPackage({ root: REPO_ROOT });
  assert.deepEqual(disallowedPaths(files), []);
  assert.ok([...files.keys()].every((p) => !p.startsWith('upload/system/storage') && !p.includes('core/upload')));
  assert.deepEqual(disallowedPaths(new Map([['upload/index.php', Buffer.from('x')], ['upload/admin/controller/common/login.php', Buffer.from('x')]])).length, 2);
});

// TEST-PKG-009 - verifies REQ-PKG-020 (no secrets, absolute dev paths or personal data)
test('package scan is clean and detects planted secrets', () => {
  assert.deepEqual(scanPackage(collectPackage({ root: REPO_ROOT })), []);
  const bad = new Map([
    ['upload/a.php', Buffer.from("<?php // C:\\Users\\someone\\project")],
    ['upload/b.php', Buffer.from('mail me: someone@gmail.com')],
    ['upload/c.php', Buffer.from('pass=Sup3rSecretValue')],
  ]);
  const found = scanPackage(bad, ['Sup3rSecretValue']);
  assert.equal(found.length, 3);
});

// TEST-PKG-010 - verifies REQ-PKG-001 (valid, deterministic archive)
test('packTheme writes a valid reproducible zip', () => {
  const out = mkdtempSync(join(tmpdir(), 'ocs-pack-'));
  const a = packTheme({ root: REPO_ROOT, outDir: out });
  assert.match(a.file, /ocs-theme-\d+\.\d+\.\d+\.ocmod\.zip$/);
  assert.ok(existsSync(a.file));
  assert.ok(a.bytes < 5 * 1024 * 1024, 'REQ-PKG-023: under 5 MB');
  const first = readFileSync(a.file);
  const b = packTheme({ root: REPO_ROOT, outDir: out });
  assert.ok(first.equals(readFileSync(b.file)), 'same inputs -> same bytes');
  const sig = first.subarray(0, 4).toString('hex');
  assert.equal(sig, '504b0304');
});

test('zip writer entries survive a round trip through php', () => {
  const files = new Map([['upload/a.txt', Buffer.from('hello hello hello hello')], ['b.bin', Buffer.from([0, 1, 2, 3, 255])]]);
  const out = join(mkdtempSync(join(tmpdir(), 'ocs-zip-')), 't.zip');
  execFileSync(process.execPath, ['-e', `require('fs').writeFileSync(${JSON.stringify(out)}, Buffer.from(${JSON.stringify(zip(files).toString('base64'))}, 'base64'))`]);
  assert.equal(crc32(Buffer.from('123456789')), 0xcbf43926);
  const buf = readFileSync(out);
  assert.equal(buf.readUInt32LE(buf.length - 22), 0x06054b50);
  assert.equal(buf.readUInt16LE(buf.length - 22 + 10), 2);
});

// TEST-DCAT-002 - verifies REQ-DCAT-006, REQ-DCAT-009, REQ-DCAT-022 (placeholder pictures: complete, small, in the package)
test('demo catalogue pictures are generated, referenced ones all exist, and stay small', async () => {
  const { demoImages, referencedImages } = await import('../lib/demo-images.mjs');
  const data = JSON.parse(readFileSync(join(REPO_ROOT, 'custom/catalog/demo/catalogue.json'), 'utf8'));
  const pictures = demoImages(data);
  for (const rel of referencedImages(data)) assert.ok(pictures.has(rel), `missing ${rel}`);
  let bytes = 0;
  for (const buf of pictures.values()) {
    assert.equal(buf.subarray(0, 8).toString('hex'), '89504e470d0a1a0a');
    bytes += buf.length;
  }
  assert.ok(bytes < 2 * 1024 * 1024, 'REQ-DCAT-006: pictures under 2 MB');
  const files = collectPackage({ root: REPO_ROOT });
  assert.ok(files.has('upload/image/catalog/ocs-demo/products/001-1.png'));
  assert.ok(files.has('upload/system/library/ocs/setup/payload/custom/catalog/demo/catalogue.json'));
  assert.deepEqual(disallowedPaths(files), []);
});

// TEST-DCAT-001 - verifies REQ-DCAT-005, REQ-DCAT-007 (texts in three languages, data shape)
test('demo catalogue data has uk/ru/en texts and the shape the theme needs', () => {
  const data = JSON.parse(readFileSync(join(REPO_ROOT, 'custom/catalog/demo/catalogue.json'), 'utf8'));
  for (const c of data.categories) assert.ok(c.name.uk && c.name.ru && c.name.en, c.code);
  for (const p of data.products) {
    assert.ok(p.description.uk && p.description.ru && p.description.en, p.code);
    assert.ok(p.price > 0);
  }
  assert.ok(data.products.length >= 36);
  const codes = new Set(data.products.map((p) => p.code));
  assert.equal(codes.size, data.products.length, 'unique product codes');
  const depth = (code) => { const c = data.categories.find((x) => x.code === code); return c.parent ? 1 + depth(c.parent) : 1; };
  assert.ok(Math.max(...data.categories.map((c) => depth(c.code))) >= 3, 'a category 3 levels deep');
});
