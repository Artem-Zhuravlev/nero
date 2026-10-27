import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

// SPEC-072: footer links switch, merged column, payment logos.
const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
const ext = 'custom/extensions/ocs/upload';

// TEST-FMN-002 - verifies REQ-FMN-001, 002, 007, 008 (form, controller, strings, setting registered)
test('footer menu: settings form, controller, language strings, extension.json', () => {
  const tw = read(`${ext}/admin/view/template/extension/theme/ocs.twig`);
  for (const hook of ['theme_ocs_footer[show][{{ item.key }}]', 'theme_ocs_footer[payments]']) assert.ok(tw.includes(hook), hook);
  assert.ok(read(`${ext}/admin/controller/extension/theme/ocs.php`).includes('Ocs\\Footer::sanitize('));
  assert.ok(JSON.parse(read('custom/extensions/ocs/extension.json')).settings.includes('theme_ocs_footer'));
  const keys = (l) => [...read(`${ext}/admin/language/${l}/extension/theme/ocs.php`).matchAll(/\$_\['([a-z_]+)'\]/g)].map((m) => m[1]).sort();
  assert.deepEqual(keys('en-gb'), keys('uk-ua'));
  assert.ok(keys('uk-ua').includes('entry_footer_payments') && keys('uk-ua').includes('text_footer_newsletter'));
  assert.ok(read(`${ext}/catalog/controller/extension/ocs/events.php`).includes("$route === 'common/footer'"));
});

// TEST-FMN-002 - verifies REQ-FMN-003, 004, 006 (template renders data groups, no separate account column, inline logos)
test('footer menu: template and styles', () => {
  const t = read('custom/theme/ocs/template/common/footer.twig');
  for (const hook of ['ocs_foot.groups', 'ocs-foot-groups--wide', 'class="ocs-pay', 'aria-label="Visa"', 'aria-label="Mastercard"', 'ocs_foot.payments']) assert.ok(t.includes(hook), hook);
  assert.ok(!t.includes('text_account }}</h2>'), 'the account column is merged into extra');
  assert.ok(!/<image|href="http/.test(t.slice(t.indexOf('ocs-pay'))), 'logos are inline SVG only');
  const c = read('custom/theme/ocs/stylesheet/stylesheet.css');
  assert.ok(c.includes('.ocs-foot-groups--wide') && c.includes('.ocs-pay svg'));
});
