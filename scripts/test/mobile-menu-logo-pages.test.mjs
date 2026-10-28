// SPEC-107: phone catalogue panel has the logo in its head and the info pages under <details>.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');

// TEST-MML-001 - verifies REQ-MML-001, REQ-MML-002, REQ-MML-003
test('mobile panel: logo in head, info pages in details, restored on desktop', () => {
  const js = read('custom/theme/ocs/javascript/theme.js');
  assert.match(js, /ocs-mnav-head/);
  assert.match(js, /collapse\.insertBefore\(head, collapse\.firstChild\)/);
  assert.match(js, /createElement\('details'\)/);
  assert.match(js, /ocs-mnav-pages/);
  assert.match(js, /box\.insertBefore\(logo, box\.firstChild\)/);
  assert.match(js, /drop\.appendChild\(menu\)/);
  const css = read('custom/theme/ocs/stylesheet/stylesheet.css');
  assert.match(css, /\.ocs-mnav-head \{/);
  assert.match(css, /\.ocs-mnav-pages > summary/);
});
