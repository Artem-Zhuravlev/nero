// SPEC-112: footer address row comes after the working hours row.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

// TEST-FAO-001 - verifies REQ-FAO-001
test('footer.twig: address row is after the hours row', () => {
  const tpl = readFileSync(join(REPO_ROOT, 'custom/theme/ocs/template/common/footer.twig'), 'utf8');
  const hours = tpl.indexOf('ocs_shell.hours %}<p');
  const address = tpl.indexOf('ocs_shell.address %}<address');
  assert.ok(hours > 0 && address > hours);
});
