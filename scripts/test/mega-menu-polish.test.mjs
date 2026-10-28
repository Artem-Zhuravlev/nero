// SPEC-103: mega menu polish.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

const css = readFileSync(join(REPO_ROOT, 'custom/theme/ocs/stylesheet/stylesheet.css'), 'utf8');
const twig = readFileSync(join(REPO_ROOT, 'custom/extensions/ocs_mega_menu/upload/catalog/view/theme/default/template/extension/module/ocs_mega_menu.twig'), 'utf8');

// TEST-MMP-001 - verifies REQ-MMP-001, REQ-MMP-002, REQ-MMP-003
test('mega menu has no see-all link, centred arrow and regular-weight children', () => {
  assert.doesNotMatch(twig, /see-all/);
  assert.match(css, /\.ocs-mega-expand\s*\{[^}]*height: var\(--ocs-mega-row\)[^}]*align-items: center/);
  assert.match(css, /\.ocs-mega-gtitle\s*\{[^}]*font-weight: 400/);
  assert.match(css, /\.ocs-mega-group ul li a\s*\{[^}]*padding: var\(--sp-1\) 0/);
});
