// SPEC-100: product card hover keeps only the shadow.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

const css = readFileSync(join(REPO_ROOT, 'custom/theme/ocs/stylesheet/stylesheet.css'), 'utf8');

// TEST-CHS-001 - verifies REQ-CHS-001, REQ-CHS-002
test('product card hover does not scale the image or move the card', () => {
  assert.doesNotMatch(css, /\.product-thumb:hover > \.image \.ocs-card-img img\s*\{[^}]*scale/);
  assert.doesNotMatch(css, /\.product-thumb:hover,\s*\.product-thumb:focus-within\s*\{[^}]*translate/);
});
