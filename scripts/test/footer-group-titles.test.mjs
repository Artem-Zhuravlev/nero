// SPEC-108: footer group titles come from the loaded language strings.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

// TEST-FGT-001 - verifies REQ-FGT-001
test('footer(): view data is completed with the language strings before Footer::view', () => {
  const php = readFileSync(join(REPO_ROOT, 'custom/extensions/ocs/upload/catalog/controller/extension/ocs/events.php'), 'utf8');
  const fn = php.slice(php.indexOf('private function footer('));
  const merge = fn.indexOf('$this->language->all()');
  assert.ok(merge > 0);
  assert.ok(merge < fn.indexOf('Footer::view'));
});
