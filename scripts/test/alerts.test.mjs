// SPEC-069: unified alerts and no click outline.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

const THEME = join(REPO_ROOT, 'custom/theme/ocs');

function files(dir, out = []) {
  for (const name of readdirSync(dir)) {
    const p = join(dir, name);
    statSync(p).isDirectory() ? files(p, out) : /\.(twig|js)$/.test(p) && out.push(p);
  }
  return out;
}

// TEST-ALR-003 - verifies REQ-ALR-001, REQ-ALR-007: no icon and no stock cross inside theme alerts
test('theme alert markup has no icons and no stock close button', () => {
  for (const f of files(join(THEME, 'template'))) {
    const src = readFileSync(f, 'utf8');
    assert.ok(!/class="alert [^"]*"[^>]*>\s*<svg/.test(src), `${f}: icon inside alert`);
    assert.ok(!/<i class="fa fa-[^"]*"><\/i>[^<]*<button type="button" class="close"/.test(src), `${f}: fa icon in alert`);
    assert.ok(!/data-dismiss="alert">&times;/.test(src), `${f}: stock cross`);
  }
});

// TEST-ALR-001, TEST-ALR-004 - verifies REQ-ALR-004, REQ-ALR-009: fixed flash alerts, click outline removed, keyboard ring kept
test('stylesheet fixes flash alerts and hides only click outline', () => {
  const css = readFileSync(join(THEME, 'stylesheet/stylesheet.css'), 'utf8');
  assert.match(css, /\.alert-dismissible\s*\{[^}]*position: fixed/);
  assert.match(css, /:focus:not\(:focus-visible\)\s*\{\s*outline: 0/);
  assert.match(css, /:focus-visible\s*\{\s*outline: 2px/);
  assert.equal((css.match(/^\s*\.alert \{/gm) || []).length, 1, 'one .alert block');
});

// TEST-ALR-002 - verifies REQ-ALR-002, REQ-ALR-006, REQ-ALR-010: normaliser strips fa icons and builds the #i-x close button
test('theme.js normaliser strips icons and adds the close button', () => {
  const js = readFileSync(join(THEME, 'javascript/theme.js'), 'utf8');
  assert.match(js, /i\.fa, \.fa, svg\.ocs-i, \.close/);
  assert.match(js, /OCS\.icon\('x'\)/);
  assert.match(js, /MutationObserver/);
  assert.match(js, /aria-label/);
});
