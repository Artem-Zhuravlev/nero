// SPEC-123 Nero settings: tabs, menu entry, brand, banned words - Node checks
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, extname } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

const read = (rel) => readFileSync(join(REPO_ROOT, rel), 'utf8');
const twigPath = 'custom/extensions/ocs/upload/admin/view/template/extension/theme/ocs.twig';

function walk(dir, out = []) {
  for (const n of readdirSync(dir)) {
    const p = join(dir, n);
    if (statSync(p).isDirectory()) walk(p, out); else out.push(p);
  }
  return out;
}

// TEST-NST-001 - verifies REQ-NST-001, REQ-NST-002
test('nero settings: tabs keep every field; contacts and social links share one tab', () => {
  const twig = read(twigPath);
  for (const id of ['general', 'contacts', 'menu', 'footer', 'blocks', 'seo', 'images']) {
    assert.ok(twig.includes(`href="#nero-${id}"`) && twig.includes(`id="nero-${id}"`), `tab ${id}`);
  }
  const names = new Set([...twig.matchAll(/name="(theme_ocs_[a-z_]+)/g)].map((m) => m[1]));
  for (const n of ['theme_ocs_font', 'theme_ocs_contact', 'theme_ocs_contact_links', 'theme_ocs_top_menu', 'theme_ocs_footer', 'theme_ocs_info_blocks', 'theme_ocs_schema_org_name', 'theme_ocs_directory']) {
    assert.ok(names.has(n), n);
  }
  const contacts = twig.slice(twig.indexOf('id="nero-contacts"'), twig.indexOf('id="nero-menu"'));
  assert.match(contacts, /theme_ocs_contact\[/);
  assert.match(contacts, /theme_ocs_contact_links\[/);
  assert.doesNotMatch(twig, /<fieldset|<legend/);
});

// TEST-NST-002 - verifies REQ-NST-005
test('nero settings: main menu entry is added after the dashboard and guarded by access', () => {
  const php = read('custom/extensions/ocs/upload/admin/controller/extension/ocs/events.php');
  assert.match(php, /hasPermission\('access', 'extension\/theme\/ocs'\)/);
  assert.match(php, /menu-dashboard/);
  assert.match(php, /'extension\/theme\/ocs'/);
  assert.match(read('custom/db/migrations/0015_nero_menu_event.php'), /admin\/view\/common\/column_left\/before/);
  assert.match(read('custom/extensions/ocs/upload/admin/language/uk-ua/extension/ocs/nero.php'), /Налаштування Nero/);
});

// TEST-NST-003 - verifies REQ-NST-006
test('nero settings: admin language values do not show the OCS brand', () => {
  const files = walk(join(REPO_ROOT, 'custom/extensions')).filter((f) => /[\\/]admin[\\/]language[\\/].*\.php$/.test(f));
  assert.ok(files.length > 20);
  for (const f of files) {
    for (const line of readFileSync(f, 'utf8').split('\n')) {
      if (line.startsWith('$_[')) assert.doesNotMatch(line, /\bOCS\b/, f);
    }
  }
});

// TEST-NST-004 - verifies REQ-NST-007, REQ-NST-008
test('nero settings: no tool mentions and no slang in shipped files', () => {
  const ext = new Set(['.php', '.twig', '.json', '.xml', '.js', '.css', '.txt', '.md']);
  const files = walk(join(REPO_ROOT, 'custom')).filter((f) => ext.has(extname(f)));
  const banned = /claude|anthropic|шмот|хрен|херн|дерьм|говн|\bсука|пизд|хуй|\bfuck|\bshit/i;
  for (const f of files) {
    const m = readFileSync(f, 'utf8').match(banned);
    assert.equal(m, null, `${f}: ${m && m[0]}`);
  }
});
