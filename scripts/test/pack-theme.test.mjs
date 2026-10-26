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

