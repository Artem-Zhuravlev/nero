// SPEC-023 / SPEC-006 theme checks: design tokens, contrast, override discipline.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

const css = readFileSync(join(REPO_ROOT, 'custom/theme/ocs/stylesheet/stylesheet.css'), 'utf8');

const blocks = () => {
  const root = /:root\s*\{([^}]*)\}/.exec(css)[1];
  const dark = /@media \(prefers-color-scheme: dark\)\s*\{\s*:root\s*\{([^}]*)\}/.exec(css)[1];
  const parse = (s) => Object.fromEntries([...s.matchAll(/--([\w-]+):\s*([^;]+);/g)].map((m) => [m[1], m[2].trim()]));
  const light = parse(root);
  return { light, dark: { ...light, ...parse(dark) } };
};

const lum = (hex) => {
  const c = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255).map((v) => (v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4));
  return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
};
const ratio = (a, b) => {
  const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p);
  return (x + 0.05) / (y + 0.05);
};

// TEST-DSG-002 - verifies REQ-DSG-002, REQ-DSG-003, REQ-DSG-004 (text contrast >= 4.5:1 in light and dark)
test('token text/background pairs keep contrast >= 4.5', () => {
  const { light, dark } = blocks();
  const pairs = [
    ['text', 'bg'], ['text', 'bg-alt'], ['text-muted', 'bg'], ['text-muted', 'bg-alt'],
    ['on-accent', 'accent'], ['on-accent', 'accent-hover'], ['on-accent', 'accent-active'],
    ['link', 'bg'], ['link', 'bg-alt'],
    ['success', 'bg'], ['success', 'bg-alt'], ['success', 'success-bg'],
    ['warning', 'bg'], ['warning', 'bg-alt'], ['warning', 'warning-bg'],
    ['danger', 'bg'], ['danger', 'bg-alt'], ['danger', 'danger-bg'],
    ['badge-fg', 'badge-bg'], ['bg', 'danger'], ['bg', 'success'],
  ];
  for (const [name, set] of [['light', light], ['dark', dark]]) {
    for (const [fg, bg] of pairs) {
      const r = ratio(set[fg], set[bg]);
      assert.ok(r >= 4.5, `${name}: --${fg} on --${bg} = ${r.toFixed(2)}`);
    }
  }
});

// TEST-DSG-001 - verifies REQ-DSG-001, REQ-DSG-002, REQ-DSG-006 (colour only in token block, required tokens exist)
test('colour literals appear only in the token section; required tokens exist', () => {
  const rest = css.slice(css.indexOf('/* ==== 2. Base'));
  assert.deepEqual(rest.match(/#[0-9a-fA-F]{3,8}\b|rgba?\(|hsla?\(/g) || [], [], 'colour literal outside section 1');
  const { light } = blocks();
  for (const t of ['bg', 'bg-alt', 'text', 'text-muted', 'border', 'accent', 'link', 'focus', 'success', 'warning', 'danger', 'r-sm', 'r-md', 'r-lg', 'shadow']) {
    assert.ok(light[t], `missing token --${t}`);
  }
  assert.equal(light.accent, '#0071e3');
  assert.equal(light.text, '#1d1d1f');
});

// TEST-THM-003 - verifies REQ-THM-010, REQ-THM-011 (override differs from default, carries @core-file header, no CDN)
test('header override is not a copy of default, has @core-file, loads ocs stylesheet with version, no CDN', () => {
  const ours = readFileSync(join(REPO_ROOT, 'custom/theme/ocs/template/common/header.twig'), 'utf8');
  const core = join(REPO_ROOT, 'core/upload/catalog/view/theme/default/template/common/header.twig');
  assert.ok(existsSync(core));
  assert.notEqual(ours, readFileSync(core, 'utf8'));
  assert.match(ours, /^\{# @core-file catalog\/view\/theme\/default\/template\/common\/header\.twig @core-version 3\.0\.3\.7 #\}/);
  assert.match(ours, /catalog\/view\/theme\/ocs\/stylesheet\/stylesheet\.css\?v=\{\{ asset_version \}\}/);
  assert.doesNotMatch(ours, /fonts\.googleapis|\/\/cdn\./);
});
