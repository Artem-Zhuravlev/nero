// Tests for SPEC-001 (manifest, env) and SPEC-003 (build). Run: npm test
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync, writeFileSync, mkdirSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { build } from '../build.mjs';
import { parseEnv, validateEnv, EnvError } from '../lib/env.mjs';
import { render, TemplateError } from '../lib/render.mjs';
import { computeManifest, checkManifest } from '../core-manifest.mjs';
import { makeRepo, baseEnv, put, REPO_ROOT } from './helpers.mjs';

const run = (root, over = {}, opts = {}) =>
  build({ root, env: baseEnv(root, over), out: join(root, 'build', 'www'), skipManifest: true, quiet: true, ...opts });

// TEST-ARCH-003 - verifies REQ-ARCH-015
test('TEST-ARCH-003 env: missing required key is rejected with its name', () => {
  assert.throws(() => validateEnv({ ...baseEnv('/x'), DB_NAME: '' }), (e) => e instanceof EnvError && /DB_NAME/.test(e.message));
});

// TEST-BLD-009 - verifies REQ-BLD-061 (exit 3 for env errors)
test('TEST-BLD-009 build: invalid env gives exit code 3', () => {
  const root = makeRepo();
  const r = run(root, { APP_URL: 'localhost' });
  assert.equal(r.exit, 3);
});

// verifies REQ-BLD-020 (ADMIN_DIR validation)
test('env: ADMIN_DIR rules', () => {
  assert.doesNotThrow(() => validateEnv(baseEnv('/x', { ADMIN_DIR: 'backoffice' })));
  assert.throws(() => validateEnv(baseEnv('/x', { ADMIN_DIR: 'catalog' })), EnvError);
  assert.throws(() => validateEnv(baseEnv('/x', { ADMIN_DIR: 'A' })), EnvError);
});

test('env: parseEnv handles comments, quotes and equals signs in values', () => {
  const e = parseEnv('# c\nA=1\nB="two words"\nC=a=b\n');
  assert.deepEqual(e, { A: '1', B: 'two words', C: 'a=b' });
});

// TEST-BLD-004 - verifies REQ-BLD-035
test('TEST-BLD-004 render: unknown token and leftovers fail, quotes are escaped', () => {
  assert.throws(() => render('{{NOPE}}', {}), TemplateError);
  assert.equal(render("'{{A}}'", { A: "it's \\" }), "'it\\'s \\\\'");
  assert.throws(() => render('{{A}}', { A: '{{' }), TemplateError);
});

// TEST-BLD-001 - verifies REQ-BLD-001
test('TEST-BLD-001 build excludes install/, config-dist, .htaccess.txt and storage from docroot', () => {
  const root = makeRepo();
  const r = run(root);
  assert.equal(r.exit, 0, r.messages.join('\n'));
  const www = join(root, 'build', 'www');
  assert.ok(existsSync(join(www, 'index.php')));
  assert.ok(!existsSync(join(www, 'install')));
  assert.ok(!existsSync(join(www, 'config-dist.php')));
  assert.ok(!existsSync(join(www, '.htaccess.txt')));
  assert.ok(!existsSync(join(www, 'system', 'storage')));
  assert.ok(existsSync(join(www, '.htaccess')));
});

// TEST-BLD-004 - verifies REQ-BLD-030, REQ-BLD-031
test('TEST-BLD-004 generated configs have all constants and no placeholders', () => {
  const root = makeRepo();
  assert.equal(run(root, { DB_PASS: "p'w\\d" }).exit, 0);
  const www = join(root, 'build', 'www');
  const cfg = readFileSync(join(www, 'config.php'), 'utf8');
  const adm = readFileSync(join(www, 'admin', 'config.php'), 'utf8');
  for (const c of ['HTTP_SERVER', 'DIR_APPLICATION', 'DIR_SYSTEM', 'DIR_IMAGE', 'DIR_STORAGE', 'DIR_TEMPLATE', 'DB_PASSWORD', 'DB_PREFIX']) {
    assert.match(cfg, new RegExp(`define\\('${c}'`));
  }
  assert.match(cfg, /DIR_TEMPLATE', DIR_APPLICATION \. 'view\/theme\/'/);
  assert.match(adm, /DIR_TEMPLATE', DIR_APPLICATION \. 'view\/template\/'/);
  assert.match(adm, /HTTP_CATALOG/);
  assert.match(cfg, /DB_PASSWORD', 'p\\'w\\\\d'/);
  assert.ok(!cfg.includes('{{') && !adm.includes('{{'));
});

// TEST-BLD-004 - verifies REQ-BLD-030, REQ-BLD-031 against the real stock installer
test('TEST-BLD-004 templates define exactly the constants the stock installer writes (core step_3.php)', () => {
  const step3 = readFileSync(join(REPO_ROOT, 'core', 'upload', 'install', 'controller', 'install', 'step_3.php'), 'utf8');
  const constants = [...step3.matchAll(/define\(\\'([A-Z_]+)\\'/g)].map((m) => m[1]);
  assert.ok(constants.length >= 40, `expected installer constants, got ${constants.length}`);
  const tpl = ['config.php.tpl', 'admin-config.php.tpl'].map((f) => readFileSync(join(REPO_ROOT, 'custom', 'config', f), 'utf8'));
  const defined = new Set(tpl.flatMap((t) => [...t.matchAll(/define\('([A-Z_]+)'/g)].map((m) => m[1])));
  const missing = [...new Set(constants)].filter((c) => !defined.has(c));
  assert.deepEqual(missing, []);
});

// TEST-BLD-003 - verifies REQ-BLD-020, REQ-BLD-021
test('TEST-BLD-003 ADMIN_DIR rewrites admin/ paths including extension files', () => {
  const root = makeRepo({
    'custom/extensions/ocs_demo/upload/admin/controller/extension/module/ocs_demo.php': '<?php // ext admin',
    'custom/extensions/ocs_demo/upload/catalog/controller/extension/module/ocs_demo.php': '<?php // ext catalog',
  });
  assert.equal(run(root, { ADMIN_DIR: 'backoffice' }).exit, 0);
  const www = join(root, 'build', 'www');
  assert.ok(existsSync(join(www, 'backoffice', 'index.php')));
  assert.ok(!existsSync(join(www, 'admin')));
  assert.ok(existsSync(join(www, 'backoffice', 'controller', 'extension', 'module', 'ocs_demo.php')));
  assert.match(readFileSync(join(www, 'backoffice', 'config.php'), 'utf8'), /HTTP_SERVER', 'http:\/\/localhost:8080\/backoffice\//);
});

// TEST-BLD-002 - verifies REQ-BLD-010
test('TEST-BLD-002 overlay file colliding with core fails with exit 2 and writes nothing', () => {
  const root = makeRepo({ 'custom/overlay/catalog/controller/common/home.php': '<?php // shadow' });
  const r = run(root);
  assert.equal(r.exit, 2);
  assert.ok(r.messages.some((m) => m.startsWith('COLLISION catalog/controller/common/home.php (core vs overlay)')));
  assert.ok(!existsSync(join(root, 'build', 'www')));
});

// verifies REQ-BLD-010 (case-insensitive) and extension-vs-extension collisions
test('TEST-BLD-002 collisions are case-insensitive and detected between extensions', () => {
  const root = makeRepo({
    'custom/extensions/ocs_a/upload/catalog/Foo.php': '1',
    'custom/extensions/ocs_b/upload/catalog/foo.php': '2',
  });
  const r = run(root);
  assert.equal(r.exit, 2);
  assert.ok(r.messages.some((m) => /COLLISION catalog\/foo\.php \(ext:ocs_a vs ext:ocs_b\)/i.test(m)));
});

// verifies REQ-BLD-033 (robots.txt exception)
test('TEST-BLD-005 overlay robots.txt may replace core robots.txt', () => {
  const root = makeRepo({ 'custom/overlay/robots.txt': 'Disallow: /\n' });
  assert.equal(run(root).exit, 0);
  assert.equal(readFileSync(join(root, 'build', 'www', 'robots.txt'), 'utf8'), 'Disallow: /\n');
});

// verifies REQ-BLD-011 (generated paths cannot be shipped)
test('TEST-BLD-002 shipping a generated path (config.php) is a collision', () => {
  const root = makeRepo({ 'custom/overlay/config.php': '<?php' });
  assert.equal(run(root).exit, 2);
});

// TEST-BLD-006 / TEST-BLD-008 - verifies REQ-BLD-034, REQ-BLD-052, REQ-BLD-014, REQ-BLD-051
test('TEST-BLD-006 TEST-BLD-008 second build is a no-op; runtime images survive; stale files are removed', () => {
  const root = makeRepo();
  const first = run(root);
  assert.equal(first.exit, 0);
  const www = join(root, 'build', 'www');
  mkdirSync(join(www, 'image', 'catalog', 'products'), { recursive: true });
  writeFileSync(join(www, 'image', 'catalog', 'products', 'p1.jpg'), 'user upload');
  writeFileSync(join(www, 'stale.php'), 'old');
  const info1 = readFileSync(join(root, 'build', 'BUILD_INFO.json'), 'utf8');
  const second = run(root);
  assert.equal(second.stats.added, 0);
  assert.equal(second.stats.updated, 0);
  assert.equal(second.stats.removed, 1);
  assert.equal(second.stats.treeHash, first.stats.treeHash);
  assert.ok(existsSync(join(www, 'image', 'catalog', 'products', 'p1.jpg')));
  assert.ok(!existsSync(join(www, 'stale.php')));
  assert.equal(readFileSync(join(root, 'build', 'BUILD_INFO.json'), 'utf8'), info1, 'BUILD_INFO kept when treeHash unchanged');
  const info = JSON.parse(info1);
  assert.equal(info.core, 'ocStore 3.0.3.7 0002');
  assert.ok(!info1.includes(root.replace(/\\/g, '/')), 'no absolute paths in BUILD_INFO');
});

// verifies REQ-BLD-040 (storage incl. vendor, outside docroot) and REQ-BLD-042 (vendor sync)
test('TEST-BLD-007 storage skeleton includes vendor and is outside the docroot', () => {
  const root = makeRepo();
  assert.equal(run(root).exit, 0);
  const st = join(root, 'var', 'storage');
  assert.ok(existsSync(join(st, 'vendor', 'autoload.php')));
  for (const d of ['cache', 'logs', 'modification', 'session', 'upload', 'download']) assert.ok(existsSync(join(st, d)));
  writeFileSync(join(st, 'vendor', 'autoload.php'), 'tampered');
  put(root, 'core/upload/system/storage/vendor/autoload.php', '<?php // upgraded');
  assert.equal(run(root).exit, 0);
  assert.equal(readFileSync(join(st, 'vendor', 'autoload.php'), 'utf8'), '<?php // upgraded');
});

// verifies REQ-BLD-040 (STORAGE_DIR must be outside docroot)
test('TEST-BLD-007 STORAGE_DIR inside the docroot is rejected with exit 4', () => {
  const root = makeRepo();
  const r = run(root, { STORAGE_DIR: join(root, 'build', 'www', 'st').replace(/\\/g, '/') + '/' });
  assert.equal(r.exit, 4);
});

// verifies REQ-BLD-060 (--check writes nothing)
test('TEST-BLD-009 --check writes nothing', () => {
  const root = makeRepo();
  const r = run(root, {}, { check: true });
  assert.equal(r.exit, 0);
  assert.ok(!existsSync(join(root, 'build')));
});

// verifies REQ-BLD-032 (htaccess)
test('TEST-BLD-005 .htaccess keeps stock rules, sets RewriteBase from APP_URL and denies sensitive paths', () => {
  const root = makeRepo();
  assert.equal(run(root, { APP_URL: 'http://localhost/shop/' }).exit, 0);
  const h = readFileSync(join(root, 'build', 'www', '.htaccess'), 'utf8');
  assert.match(h, /RewriteBase \/shop\//);
  assert.match(h, /RewriteRule \^system\/storage\//);
  assert.match(h, /RewriteRule \^\(custom\|specs\|docs\|scripts\|var\|build\|core\)\//);
});

// TEST-ARCH-001 - verifies REQ-ARCH-010
test('TEST-ARCH-001 manifest detects changed, added and missing core files', () => {
  const root = makeRepo();
  const core = join(root, 'core');
  const mf = join(core, '.manifest.sha256');
  writeFileSync(mf, computeManifest(core).join('\n') + '\n');
  assert.equal(checkManifest(core, mf).ok, true);
  writeFileSync(join(core, 'upload', 'index.php'), '<?php // edited');
  put(root, 'core/upload/new.php', '1');
  rmSync(join(core, 'upload', 'robots.txt'));
  const r = checkManifest(core, mf);
  assert.equal(r.ok, false);
  assert.deepEqual(r.changed, ['upload/index.php']);
  assert.deepEqual(r.added, ['upload/new.php']);
  assert.deepEqual(r.missing, ['upload/robots.txt']);
  const b = build({ root, env: baseEnv(root), quiet: true });
  assert.equal(b.exit, 5, 'build refuses a modified core');
});
