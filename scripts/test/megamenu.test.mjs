import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

// SPEC-055: mega menu flyout spans the list height; banner is a right-hand column.
const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
const EXT = 'custom/extensions/ocs_mega_menu/upload/catalog/';

// TEST-MMF-001 - verifies REQ-MMF-001, REQ-MMF-005
test('open flyout is anchored to the list with full height and no hover gap', () => {
  const css = read('custom/theme/ocs/stylesheet/stylesheet.css');
  const m = css.match(/\.ocs-mega-item\.open > \.ocs-mega-fly \{\s*display: flex;[^}]*\}/);
  assert.ok(m, 'desktop flyout rule');
  assert.match(m[0], /top: 0; bottom: 0; left: 100%/);
  assert.ok(!/margin-left/.test(m[0]));
  assert.match(css, /\.ocs-mega-item \{ position: static; \}/);
});

// TEST-MMF-002 - verifies REQ-MMF-003, REQ-MMF-004, REQ-MMF-007
test('banner is a flyout column sibling of the body and requested at 480x720', () => {
  const tw = read(EXT + 'view/theme/default/template/extension/module/ocs_mega_menu.twig');
  const body = tw.indexOf('class="ocs-mega-body"');
  const promo = tw.indexOf('class="ocs-mega-promo"');
  assert.ok(body > 0 && promo > body);
  assert.ok(tw.includes('width="240" height="360" loading="lazy"'));
  assert.ok(read(EXT + 'controller/extension/module/ocs_mega_menu.php').includes("$node['banner_image'], 480, 720"));
});
