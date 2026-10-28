// SPEC-086 special price countdown checks.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
const EV = 'custom/extensions/ocs/upload/catalog/controller/extension/ocs/events.php';

// TEST-TMR-001 - verifies REQ-TMR-001, REQ-TMR-003, REQ-TMR-004, REQ-TMR-005 (event selects the special the core applies and passes seconds left only when positive)
test('special countdown: event passes the seconds left of the applied special', () => {
  const s = read(EV);
  assert.match(s, /TIMESTAMPDIFF\(SECOND, NOW\(\), date_end\)/);
  assert.match(s, /date_end = '0000-00-00' OR date_end > NOW\(\)\) ORDER BY priority ASC, price ASC LIMIT 1/);
  assert.match(s, /\$data\['ocs_special_timer'\] = null;/);
  assert.match(s, /\(int\)\$end\['seconds'\] > 0/);
});

// TEST-TMR-002 - verifies REQ-TMR-002, REQ-TMR-004, REQ-TMR-005, REQ-TMR-012 (markup only with a timer, accessible, script hides it at zero)
test('special countdown: template, styles and script', () => {
  const t = read('custom/theme/ocs/template/product/product.twig');
  assert.match(t, /\{% if ocs_special_timer %\}[\s\S]*role="timer" aria-live="off" data-ocs-timer="\{\{ ocs_special_timer\.seconds \}\}"/);
  assert.ok(read('custom/theme/ocs/stylesheet/stylesheet.css').includes('.ocs-special-timer'));
  const js = read('custom/theme/ocs/javascript/theme.js');
  assert.match(js, /data-ocs-timer/);
  assert.match(js, /el\.hidden = true/);
});

// TEST-TMR-003 - verifies REQ-TMR-013 (strings in all three languages)
test('special countdown: strings exist in uk-ua, ru-ru, en-gb', () => {
  for (const l of ['uk-ua', 'ru-ru', 'en-gb']) {
    const s = read(`custom/extensions/ocs/upload/catalog/language/${l}/extension/ocs/top.php`);
    assert.match(s, /text_special_timer/);
    assert.match(s, /text_special_days/);
  }
});
