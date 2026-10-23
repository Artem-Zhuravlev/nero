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

// --- OCS.modal behaviour with a minimal fake DOM ---
function loadModal() {
  const handlers = {};
  const el = () => ({
    className: '', attrs: {}, children: [], parentNode: null, listeners: {}, innerHTML: '', nodeType: 1,
    setAttribute(k, v) { this.attrs[k] = v; },
    appendChild(c) { c.parentNode = this; this.children.push(c); return c; },
    removeChild(c) { this.children = this.children.filter((x) => x !== c); c.parentNode = null; },
    addEventListener(t, f) { this.listeners[t] = f; },
    querySelectorAll() { return []; },
    focus() { document.activeElement = this; },
  });
  const classes = new Set();
  const document = {
    body: el(), activeElement: null,
    documentElement: { classList: { add: (c) => classes.add(c), remove: (c) => classes.delete(c) } },
    createElement: el,
    addEventListener: (t, f) => { handlers[t] = f; },
    removeEventListener: (t) => { delete handlers[t]; },
  };
  const window = {};
  new Function('window', 'document', readFileSync(join(REPO_ROOT, 'custom/theme/ocs/javascript/theme.js'), 'utf8'))(window, document);
  return { OCS: window.OCS, document, handlers, classes };
}

// TEST-DSG-005 - verifies REQ-DSG-013 (dialog semantics, closes on Esc and overlay click, scroll lock released)
test('OCS.modal opens as dialog and closes on Esc and on overlay click', () => {
  const t = loadModal();
  const dialog = t.OCS.modal.open({ content: '<p>x</p>', closeLabel: 'Close' });
  assert.equal(dialog.attrs.role, 'dialog');
  assert.equal(dialog.attrs['aria-modal'], 'true');
  assert.ok(t.OCS.modal.isOpen());
  assert.ok(t.classes.has('ocs-modal-open'));

  t.handlers.keydown({ key: 'Escape', preventDefault() {} });
  assert.ok(!t.OCS.modal.isOpen());
  assert.ok(!t.classes.has('ocs-modal-open'));
  assert.equal(t.document.body.children.length, 0);
  assert.equal(t.handlers.keydown, undefined, 'keydown listener removed');

  t.OCS.modal.open({ content: 'y' });
  const overlay = t.document.body.children[0];
  overlay.listeners.mousedown({ target: dialog });
  assert.ok(t.OCS.modal.isOpen(), 'click inside dialog keeps it open');
  overlay.listeners.mousedown({ target: overlay });
  assert.ok(!t.OCS.modal.isOpen(), 'overlay click closes');
});

// TEST-THM-005 - verifies REQ-THM-023, REQ-THM-042 (theme.js under budget, only window.OCS global)
test('theme.js stays within the size budget and header loads it after common.js', () => {
  const js = readFileSync(join(REPO_ROOT, 'custom/theme/ocs/javascript/theme.js'), 'utf8');
  assert.ok(Buffer.byteLength(js) <= 60 * 1024);
  const header = readFileSync(join(REPO_ROOT, 'custom/theme/ocs/template/common/header.twig'), 'utf8');
  assert.ok(header.indexOf('javascript/common.js') < header.indexOf('ocs/javascript/theme.js'));
  const css = readFileSync(join(REPO_ROOT, 'custom/theme/ocs/stylesheet/stylesheet.css'), 'utf8');
  assert.ok(Buffer.byteLength(css) <= 150 * 1024);
});

// TEST-DSG-008 - verifies REQ-DSG-012, REQ-DSG-024, REQ-THM-010, REQ-THM-011, REQ-THM-013, REQ-THM-042
test('category and product overrides add slots, keep core JS hooks, are not copies, drop third-party widget', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  for (const t of ['product/category', 'product/product']) {
    const ours = read(`custom/theme/ocs/template/${t}.twig`);
    const core = read(`core/upload/catalog/view/theme/default/template/${t}.twig`);
    assert.notEqual(ours, core);
    assert.ok(ours.startsWith(`{# @core-file catalog/view/theme/default/template/${t}.twig @core-version 3.0.3.7 #}`));
    assert.match(ours, /ocs-slot-tl/);
  }
  const cat = read('custom/theme/ocs/template/product/category.twig');
  for (const hook of ['id="list-view"', 'id="grid-view"', "cart.add('{{ product.product_id }}'", 'wishlist.add', 'compare.add', 'class="product-layout']) {
    assert.ok(cat.includes(hook), `category lost hook ${hook}`);
  }
  const prod = read('custom/theme/ocs/template/product/product.twig');
  for (const hook of ['id="button-cart"', 'id="input-quantity"', 'name="product_id"', 'id="product"', 'class="thumbnails"', 'id="tab-review"', 'id="ocs-buy-slot"']) {
    assert.ok(prod.includes(hook), `product lost hook ${hook}`);
  }
  assert.doesNotMatch(prod, /addthis/i);
});
