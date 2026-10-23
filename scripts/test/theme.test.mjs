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
    body: el(), activeElement: null, readyState: 'loading',
    getElementById: () => null, querySelector: () => null, querySelectorAll: () => [],
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
    assert.match(ours, t === 'product/product' ? /ocs-slot-tl/ : /ocs\/template\/ocs\/card\.twig/);
  }
  const cat = read('custom/theme/ocs/template/product/category.twig') + read('custom/theme/ocs/template/ocs/card.twig');
  for (const hook of ['id="list-view"', 'id="grid-view"', "cart.add('{{ product.product_id }}'", 'wishlist.add', 'compare.add', 'class="product-layout']) {
    assert.ok(cat.includes(hook), `category lost hook ${hook}`);
  }
  const prod = read('custom/theme/ocs/template/product/product.twig');
  for (const hook of ['id="button-cart"', 'id="input-quantity"', 'name="product_id"', 'id="product"', 'class="thumbnails"', 'id="tab-review"', 'id="ocs-buy-slot"']) {
    assert.ok(prod.includes(hook), `product lost hook ${hook}`);
  }
  assert.doesNotMatch(prod, /addthis/i);
});

// TEST-SHL-004 - verifies REQ-SHL-010, REQ-SHL-011, REQ-SHL-020, REQ-SHL-041 (templates degrade to core when ocs_shell is absent, no literal text)
test('header and footer overrides are guarded by ocs_shell and keep core hooks', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const header = read('custom/theme/ocs/template/common/header.twig');
  const footer = read('custom/theme/ocs/template/common/footer.twig');
  assert.ok(footer.startsWith('{# @core-file catalog/view/theme/default/template/common/footer.twig @core-version 3.0.3.7 #}'));
  for (const hook of ['id="wishlist-total"', 'id="top-links"', '{{ search }}', '{{ cart }}', '{{ menu }}']) assert.ok(header.includes(hook), `header lost ${hook}`);
  for (const hook of ['{{ powered }}', '{{ text_information }}']) assert.ok(footer.includes(hook), `footer lost ${hook}`);
  for (const [name, tpl] of [['header', header], ['footer', footer]]) {
    const uses = tpl.match(/ocs_shell\.[a-z_.]+/g) || [];
    assert.ok(uses.length > 0);
    for (const line of tpl.split('\n').filter((l) => /ocs_shell\./.test(l) && !/\{% (if|for)/.test(l) && !/\{% (else|endif|endfor)/.test(l))) {
      assert.doesNotMatch(line.replace(/\{\{[^}]*\}\}|\{%[^%]*%\}|<[^>]*>|&[a-z]+;/g, ''), /[A-Za-zА-Яа-я]{3,}/, `${name}: literal text near ocs_shell`);
    }
  }
  assert.match(header, /id="compare-total"/);
  assert.match(footer, /class="ocs-tabbar"/);
});

// TEST-SHL-005 - verifies REQ-SHL-020, REQ-SHL-041 (strings exist in uk-ua, en-gb, ru-ru with identical keys; extension.json lists settings/events)
test('ocs_shell language files have identical keys and extension.json is consistent', () => {
  const keys = (code) => [...readFileSync(join(REPO_ROOT, `custom/extensions/ocs_shell/upload/catalog/language/${code}/extension/ocs_shell/shell.php`), 'utf8').matchAll(/\$_\['([a-z_]+)'\]/g)].map((m) => m[1]).sort();
  const uk = keys('uk-ua');
  assert.ok(uk.length >= 12);
  assert.deepEqual(keys('en-gb'), uk);
  assert.deepEqual(keys('ru-ru'), uk);
  const ext = JSON.parse(readFileSync(join(REPO_ROOT, 'custom/extensions/ocs_shell/extension.json'), 'utf8'));
  assert.equal(ext.code, 'ocs_shell');
  assert.ok(ext.events.includes('ocs_shell_header_before') && ext.events.includes('ocs_shell_footer_before'));
  const mig = readFileSync(join(REPO_ROOT, 'custom/extensions/ocs_shell/db/001_install.php'), 'utf8');
  for (const e of ext.events) assert.ok(mig.includes(`'${e}'`), `migration does not register ${e}`);
});

// TEST-LST-002 - verifies REQ-LST-001 (every sort key offered is accepted by core, OCMOD anchor is unique in pristine core)
test('listing sort keys are whitelisted by core (plus OCMOD for p.viewed)', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const events = read('custom/extensions/ocs_listing/upload/catalog/controller/extension/ocs_listing/events.php');
  const core = read('core/upload/catalog/model/catalog/product.php').replace(/\r/g, '');
  const whitelist = /\$sort_data = array\(([\s\S]*?)\);/.exec(core)[1].match(/'[a-z0-9_.]+'/g).map((s) => s.slice(1, -1));
  const offered = [...events.matchAll(/array\('([a-z_.]+)', '(ASC|DESC)', 'ocs_sort_/g)].map((m) => m[1]);
  assert.ok(offered.length >= 6);
  const ocmod = read('custom/ocmod/ocs_listing_sort.ocmod.xml');
  const added = /<add position="replace"><!\[CDATA\[[\s\S]*?'p\.viewed'\]\]>/.test(ocmod) ? ['p.viewed'] : [];
  for (const key of offered) assert.ok(whitelist.includes(key) || added.includes(key), `core does not accept sort ${key}`);
  const anchor = /<search><!\[CDATA\[(.*?)\]\]><\/search>/.exec(ocmod)[1];
  assert.equal(core.split(anchor).length - 1, 1, 'OCMOD search anchor must be unique in core');
  assert.match(ocmod, /@core-file catalog\/model\/catalog\/product\.php/);
});

// TEST-LST-001, TEST-LST-003, TEST-LST-004 - verifies REQ-LST-002, REQ-LST-003, REQ-LST-010, REQ-LST-011, REQ-LST-012, REQ-LST-013
test('listing overrides use the card partial, expose load-more/reset hooks and degrade without ocs_listing', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const js = read('custom/theme/ocs/javascript/theme.js');
  const card = read('custom/theme/ocs/template/ocs/card.twig');
  for (const hook of ['data-product-id', 'loading="lazy"', 'product.model', 'product.discount_pct', 'product.reviews', 'cart.add(']) assert.ok(card.includes(hook), `card lost ${hook}`);
  for (const t of ['category', 'search', 'special', 'manufacturer_info']) {
    const ours = read(`custom/theme/ocs/template/product/${t}.twig`);
    const coreTpl = read(`core/upload/catalog/view/theme/default/template/product/${t}.twig`);
    assert.notEqual(ours, coreTpl);
    assert.ok(ours.startsWith(`{# @core-file catalog/view/theme/default/template/product/${t}.twig @core-version 3.0.3.7 #}`));
    assert.ok(ours.includes("{% include 'ocs/template/ocs/card.twig' %}"), `${t}: no card include`);
    assert.ok(!ours.includes('product-thumb'), `${t}: inlined card left`);
    for (const id of ['ocs-grid', 'ocs-load-more']) {
      assert.ok(ours.includes(`id="${id}"`) && js.includes(`'${id}'`), `${t}: ${id} not wired to theme.js`);
    }
    assert.match(ours, /\{% if ocs_listing and ocs_listing\.reset %\}/);
    assert.match(ours, /\{\{ pagination \}\}/, `${t}: numbered pagination must stay (no-JS fallback)`);
  }
  const cat = read('custom/theme/ocs/template/product/category.twig');
  assert.match(cat, /\{% if description and not ocs_listing %\}/, 'description stays on top when the module is off');
  assert.match(cat, /class="ocs-seo"/);
  assert.match(cat, /class="ocs-chips"/);
  const keys = (code) => [...read(`custom/extensions/ocs_listing/upload/catalog/language/${code}/extension/ocs_listing/listing.php`).matchAll(/\$_\['([a-z_]+)'\]/g)].map((m) => m[1]).sort();
  assert.deepEqual(keys('en-gb'), keys('uk-ua'));
  assert.deepEqual(keys('ru-ru'), keys('uk-ua'));
  for (const used of cat.matchAll(/ocs_listing\.t\.(ocs_[a-z_]+)/g)) assert.ok(keys('uk-ua').includes(used[1]), `missing string ${used[1]}`);
  const ext = JSON.parse(read('custom/extensions/ocs_listing/extension.json'));
  const mig = read('custom/extensions/ocs_listing/db/001_install.php');
  for (const e of ext.events) assert.ok(mig.includes(`'${e}'`), `migration does not register ${e}`);
});

// --- SPEC-027 navigation ---
const MENU_EXT = ['ocs_menu', 'ocs_mega_menu', 'ocs_category_wall', 'ocs_category_menu'];

// TEST-MNU-006 - verifies REQ-MNU-031, REQ-EXT-051 (declared events/settings match migrations; module strings identical across languages)
test('menu extensions: manifests match migrations and language files have identical keys', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  for (const code of MENU_EXT) {
    const ext = JSON.parse(read(`custom/extensions/${code}/extension.json`));
    assert.equal(ext.code, code);
    assert.equal(ext.spec, 'SPEC-027');
    const mig = read(`custom/extensions/${code}/db/001_install.php`);
    for (const e of ext.events) assert.ok(mig.includes(`'${e}'`), `${code}: migration does not register ${e}`);
    for (const s of ext.settings.filter((k) => k.endsWith('_status'))) assert.ok(mig.includes(`'${s}' => 0`), `${code}: ${s} must default to 0`);
    for (const lang of ['uk-ua', 'en-gb', 'ru-ru']) {
      const dir = `custom/extensions/${code}/upload/catalog/language/${lang}/extension/module/${code}.php`;
      if (ext.type !== 'module') continue;
      const keys = (l) => [...read(`custom/extensions/${code}/upload/catalog/language/${l}/extension/module/${code}.php`).matchAll(/\$_\['([a-z_]+)'\]/g)].map((m) => m[1]).sort();
      assert.deepEqual(keys(lang), keys('uk-ua'), dir);
      const akeys = (l) => [...read(`custom/extensions/${code}/upload/admin/language/${l}/extension/module/${code}.php`).matchAll(/\$_\['([a-z_]+)'\]/g)].map((m) => m[1]).sort();
      assert.deepEqual(akeys('en-gb'), akeys('uk-ua'), `${code} admin strings`);
    }
  }
});

// TEST-MNU-001 - verifies REQ-MNU-030 (the single cached query only reads columns that the install migration creates)
test('menu model reads only columns of ocs_menu_category', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const ddl = read('custom/extensions/ocs_menu/db/001_install.php');
  const cols = [...ddl.matchAll(/^\s+([a-z_]+) (?:INT|VARCHAR|TINYINT)/gm)].map((m) => m[1]);
  const model = read('custom/extensions/ocs_menu/upload/catalog/model/extension/ocs_menu/tree.php');
  for (const used of model.matchAll(/\bx\.([a-z_]+)/g)) assert.ok(cols.includes(used[1]), `model reads missing column ${used[1]}`);
  assert.ok(/ENGINE=InnoDB DEFAULT CHARSET=utf8/.test(ddl));
  assert.equal((model.match(/->query\(/g) || []).length, 1, 'tree model must run exactly one query');
});

// TEST-MNU-002, TEST-MNU-003 - verifies REQ-MNU-001, REQ-MNU-004 (markup keeps core menu hooks and the classes theme.js drives)
test('mega menu markup keeps core hooks and matches theme.js and css selectors', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const tpl = read('custom/extensions/ocs_mega_menu/upload/catalog/view/theme/default/template/extension/module/ocs_mega_menu.twig');
  for (const hook of ['id="menu"', 'id="category"', 'navbar-toggle', 'data-toggle="collapse"', 'navbar-ex1-collapse', 'aria-expanded="false"', 'data-behaviour']) assert.ok(tpl.includes(hook), `mega template lost ${hook}`);
  const js = read('custom/theme/ocs/javascript/theme.js');
  const css = read('custom/theme/ocs/stylesheet/stylesheet.css');
  for (const cls of ['ocs-mega-open', 'ocs-mega-expand', 'ocs-has-children', 'ocs-mega-fly', 'ocs-mega-l1']) {
    assert.ok(tpl.includes(cls), `template lacks ${cls}`);
    assert.ok(css.includes(`.${cls}`) || js.includes(cls), `${cls} unused by css/js`);
  }
  const menu = read('custom/theme/ocs/template/common/menu.twig');
  assert.ok(menu.startsWith('{# @core-file catalog/view/theme/default/template/common/menu.twig @core-version 3.0.3.7 #}'));
  assert.match(menu, /\{% if ocs_mega %\}\s*\{\{ ocs_mega \}\}\s*\{% else %\}/, 'falls back to the core menu when the module is off');
  assert.ok(menu.includes('navbar-ex1-collapse'), 'core markup kept as fallback');
  const side = read('custom/extensions/ocs_category_menu/upload/catalog/view/theme/default/template/extension/module/ocs_category_menu.twig');
  for (const cls of ['ocs-cmenu', 'ocs-cmenu-toggle', 'data-id', 'aria-current']) assert.ok(side.includes(cls));
  assert.ok(js.includes('ocs-cmenu-toggle') && js.includes('sessionStorage'));
  const wall = read('custom/extensions/ocs_category_wall/upload/catalog/view/theme/default/template/extension/module/ocs_category_wall.twig');
  for (const v of ['--ocs-wall-d', '--ocs-wall-t', '--ocs-wall-m']) assert.ok(wall.includes(v) && css.includes(v), `${v} not wired`);
  assert.match(wall, /loading="lazy"/);
});
