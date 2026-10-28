// SPEC-105: arrow on every mega menu item with children.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

const css = readFileSync(join(REPO_ROOT, 'custom/theme/ocs/stylesheet/stylesheet.css'), 'utf8');

// TEST-MMA-001 - verifies REQ-MMA-001
test('desktop arrow is in the row flow, not absolute against the static item', () => {
  assert.match(css, /\.ocs-mega-item\.ocs-has-children\s*\{[^}]*display: flex/);
  assert.match(css, /\.ocs-has-children > \.ocs-mega-expand\s*\{[^}]*position: relative/);
});
