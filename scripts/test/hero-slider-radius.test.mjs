// SPEC-109: hero slide has no grey gap under the photo and the photo is rounded.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

// TEST-HRR-001 - verifies REQ-HRR-001
test('hero slide: picture is block, slide has no background, img is rounded', () => {
  const css = readFileSync(join(REPO_ROOT, 'custom/theme/ocs/stylesheet/stylesheet.css'), 'utf8');
  assert.match(css, /\.ocs-hero-slide picture \{ display: block; \}/);
  assert.match(css, /\.ocs-hero-slide img \{[^}]*border-radius: var\(--r-lg\)/);
  assert.doesNotMatch(css, /\.ocs-hero \.ocs-carousel-item \{[^}]*background: var\(--bg-alt\)/);
});
