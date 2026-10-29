// SPEC-121 theme font choice - Node checks (PHP side: scripts/test/php/FontsTest.php)
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

const dir = join(REPO_ROOT, 'custom/theme/ocs/fonts');
const bundled = ['montserrat', 'inter', 'manrope', 'onest', 'golos-text', 'nunito-sans'];

// TEST-FCH-003 - verifies REQ-FCH-005, REQ-FCH-013 (bundled fonts: all weights in latin + cyrillic, licences listed)
test('theme fonts: bundled families have every weight and their licence is listed', () => {
  const readme = readFileSync(join(dir, 'README.txt'), 'utf8');
  for (const f of bundled) {
    for (const s of ['latin', 'cyrillic']) for (const w of [400, 500, 600, 700, 800]) {
      const p = join(dir, `${f}-${s}-${w}.woff2`);
      assert.ok(existsSync(p) && statSync(p).size > 1000, `${f} ${s} ${w}`);
    }
    assert.ok(readme.toLowerCase().includes(f.replace('-', ' ')) || readme.toLowerCase().includes(f), `licence of ${f}`);
  }
  assert.match(readme, /Open Font License/);
});

// TEST-FCH-001 - verifies REQ-FCH-001 (admin form, white-list validation, event wiring)
test('theme fonts: form field, validation through Fonts::resolve and header wiring', () => {
  const ctl = readFileSync(join(REPO_ROOT, 'custom/extensions/ocs/upload/admin/controller/extension/theme/ocs.php'), 'utf8');
  assert.ok(ctl.includes("'font' => 'montserrat'") && ctl.includes('Ocs\\Fonts::resolve('));
  const tpl = readFileSync(join(REPO_ROOT, 'custom/extensions/ocs/upload/admin/view/template/extension/theme/ocs.twig'), 'utf8');
  assert.ok(tpl.includes('name="theme_ocs_font"'));
  const ev = readFileSync(join(REPO_ROOT, 'custom/extensions/ocs/upload/catalog/controller/extension/ocs/events.php'), 'utf8');
  assert.ok(ev.includes('Ocs\\Fonts::render('));
  const header = readFileSync(join(REPO_ROOT, 'custom/theme/ocs/template/common/header.twig'), 'utf8');
  assert.ok(header.includes('font_css') && header.includes('font_preload'));
  assert.ok(!/fonts.(googleapis|gstatic).com/.test(header), 'no third-party font request');
});
