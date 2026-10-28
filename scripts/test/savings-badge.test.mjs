// SPEC-091 savings badge checks.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
const EV = 'custom/extensions/ocs/upload/catalog/controller/extension/ocs/events.php';

// TEST-SAV-001 - verifies REQ-SAV-001, REQ-SAV-002, REQ-SAV-004 (event computes the taxed difference in the visitor currency, only when positive)
test('savings badge: event computes the difference', () => {
  const s = read(EV);
  assert.match(s, /\$data\['ocs_savings'\] = '';/);
  assert.match(s, /\$this->tax->calculate\(\$info\['price'\][^;]*- \$this->tax->calculate\(\$info\['special'\]/);
  assert.match(s, /format\(\$saved, \$currency, '', false\) > 0/);
});

// TEST-SAV-002 - verifies REQ-SAV-003, REQ-SAV-005 (badge only with a value, styled)
test('savings badge: template and styles', () => {
  assert.match(read('custom/theme/ocs/template/product/product.twig'), /\{% if ocs_savings %\}[^]*class="ocs-savings"/);
  assert.ok(read('custom/theme/ocs/stylesheet/stylesheet.css').includes('.ocs-savings'));
});

// TEST-SAV-003 - verifies REQ-SAV-012 (strings in all three languages)
test('savings badge: strings exist in uk-ua, ru-ru, en-gb', () => {
  for (const l of ['uk-ua', 'ru-ru', 'en-gb']) {
    assert.match(read(`custom/extensions/ocs/upload/catalog/language/${l}/extension/ocs/top.php`), /\$_\['text_savings'\] = '[^']+';/);
  }
});
