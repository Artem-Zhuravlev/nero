// SPEC-023 / SPEC-006 theme checks: design tokens, contrast, override discipline.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, existsSync, readdirSync } from 'node:fs';
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
  assert.ok(Buffer.byteLength(css) <= 200 * 1024);
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
    for (const s of ext.settings.filter((k) => k.endsWith('_status'))) assert.match(mig, new RegExp(`'${s}'(\\s*=>\\s*|,\\s*)0`), `${code}: ${s} must default to 0`);
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
  // one cached query for the rows, one cached query for the optional brand group (REQ-MNU-030)
  assert.equal((model.match(/->query\(/g) || []).length, 2, 'tree model runs exactly two queries');
  assert.equal((model.match(/cache->set\(/g) || []).length, 2, 'both queries are cached');
});

// TEST-MNU-002, TEST-MNU-003 - verifies REQ-MNU-001, REQ-MNU-004 (markup keeps core menu hooks and the classes theme.js drives)
test('mega menu markup keeps core hooks and matches theme.js and css selectors', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const tpl = read('custom/extensions/ocs_mega_menu/upload/catalog/view/theme/default/template/extension/module/ocs_mega_menu.twig');
  for (const hook of ['id="menu"', 'id="category"', 'navbar-toggle', 'data-toggle="collapse"', 'navbar-ex1-collapse', 'aria-expanded="false"', 'data-behaviour']) assert.ok(tpl.includes(hook), `mega template lost ${hook}`);
  const js = read('custom/theme/ocs/javascript/theme.js');
  const css = read('custom/theme/ocs/stylesheet/stylesheet.css');
  for (const cls of ["ocs-mega-open", "ocs-mega-expand", "ocs-has-children", "ocs-mega-fly", "ocs-mega-l1", "ocs-mega-close"]) {
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

// TEST-SHL-001 - verifies REQ-SHL-001 (regression of the first live run: per-language settings use the language directory code)
test('shell events key per-language settings by config_language, not the HTML language code', () => {
  const src = readFileSync(join(REPO_ROOT, 'custom/extensions/ocs_shell/upload/catalog/controller/extension/ocs_shell/events.php'), 'utf8');
  assert.match(src, /\$code = \$this->config->get\('config_language'\)/);
  assert.doesNotMatch(src, /\$code = \$this->language->get\('code'\)/);
});

// --- SPEC-025 request forms ---
const REQ_EXT = ['ocs_request', 'ocs_quick_order', 'ocs_preorder', 'ocs_back_in_stock'];

// TEST-QOR-001..004 - verifies REQ-QOR-030, REQ-EXT-051 (manifests match migrations; language files have identical keys; modules default to disabled)
test('request extensions: manifests match migrations and language files have identical keys', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const keys = (p) => [...read(p).matchAll(/\$_\['([a-z_]+)'\]/g)].map((m) => m[1]).sort();
  for (const code of REQ_EXT) {
    const ext = JSON.parse(read(`custom/extensions/${code}/extension.json`));
    assert.equal(ext.code, code);
    assert.equal(ext.spec, 'SPEC-025');
    if (ext.type !== 'module') continue;
    const mig = readdirSync(join(REPO_ROOT, `custom/extensions/${code}/db`)).map((n) => read(`custom/extensions/${code}/db/${n}`)).join('\n');
    for (const e of ext.events) assert.ok(mig.includes(`'${e}'`), `${code}: migration does not register ${e}`);
    for (const s of ext.settings.filter((k) => k.endsWith('_status'))) assert.match(mig, new RegExp(`'${s}'(\\s*=>\\s*|,\\s*)0`), `${code}: ${s} must default to 0`);
    const cat = (l) => keys(`custom/extensions/${code}/upload/catalog/language/${l}/extension/module/${code}.php`);
    assert.deepEqual(cat('en-gb'), cat('uk-ua'));
    assert.deepEqual(cat('ru-ru'), cat('uk-ua'));
    const adm = (l) => keys(`custom/extensions/${code}/upload/admin/language/${l}/extension/module/${code}.php`);
    assert.deepEqual(adm('en-gb'), adm('uk-ua'), `${code} admin strings`);
    for (const t of ext.tables || []) assert.ok(mig.includes(t.replace(/^oc_/, '{{PREFIX}}')), `${code}: table ${t} not created by migration`);
  }
});

// TEST-QOR-002, TEST-QOR-003, TEST-QOR-004 - verifies REQ-QOR-001, REQ-QOR-010, REQ-QOR-020 (theme hooks, JS and shared form template are wired)
test('request popups: buttons, JS handler, shared form and error codes are consistent', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const js = read('custom/theme/ocs/javascript/theme.js');
  assert.ok(js.includes("'ocs-req-btn'") && js.includes('OCS.req') && js.includes('data-collect'));
  const prod = read('custom/theme/ocs/template/product/product.twig');
  for (const cls of ['ocs-preorder-btn', 'ocs-quick-btn', 'ocs-subscribe-btn', 'data-form', 'data-collect="1"']) assert.ok(prod.includes(cls), `product.twig lacks ${cls}`);
  assert.match(prod, /class="ocs-preorder"/);
  assert.match(read('custom/theme/ocs/template/ocs/card.twig'), /ocs-req-btn[^>]*data-form="\{\{ ocs_quick\.form \}\}"/);
  const css = read('custom/theme/ocs/stylesheet/stylesheet.css');
  assert.match(css, /\.ocs-preorder #button-cart \{ display: none; \}/);
  const form = read('custom/extensions/ocs_request/upload/catalog/view/theme/default/template/extension/ocs_request/form.twig');
  for (const hook of ['name="token"', 'name="website"', 'data-error-for="_form"', 'data-failed', 'data-close', 'ocs-req-done']) assert.ok(form.includes(hook), `form lacks ${hook}`);
  for (const code of ['ocs_quick_order', 'ocs_preorder', 'ocs_back_in_stock']) {
    const strings = [...read(`custom/extensions/${code}/upload/catalog/language/uk-ua/extension/module/${code}.php`).matchAll(/\$_\['([a-z_]+)'\]/g)].map((m) => m[1]);
    for (const k of ['error_required', 'error_format', 'error_rate', 'error_token', 'error_generic', 'text_success', 'text_close']) assert.ok(strings.includes(k), `${code} lacks ${k}`);
    const ctl = read(`custom/extensions/${code}/upload/catalog/controller/extension/module/${code}.php`);
    assert.match(ctl, /tokenValid/);
    assert.match(ctl, /allow\(/);
  }
});

// TEST-QOR-002 - verifies REQ-QOR-002 (the order is built by the private cart, prices are never read from the request)
test('quick order never trusts posted prices and does not touch the visitor cart', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const order = read('custom/extensions/ocs_request/upload/catalog/model/extension/ocs_request/order.php');
  assert.match(order, /new \\Ocs\\IsolatedSession\(\)/);
  assert.match(order, /new \\Ocs\\IsolatedCustomer\(\)/);
  assert.match(order, /new \\Cart\\Cart\(\$registry\)/);
  assert.doesNotMatch(order, /request->post/);
  assert.doesNotMatch(order, /\$this->cart->/);
  const pre = read('custom/extensions/ocs_preorder/upload/catalog/controller/extension/module/ocs_preorder.php');
  assert.match(pre, /config_processing_status/, 'pre-order order status must exclude stock-subtracting statuses');
  assert.match(pre, /order_id/);
});

// --- SPEC-026 AJAX cart ---
// TEST-CRT-001..004 - verifies REQ-CRT-002, REQ-CRT-003, REQ-CRT-005, REQ-CRT-010, REQ-CRT-021 (manifest, endpoints, panel markup, JS hooks, switch-off)
test('ajax cart: manifest, endpoints, panel markup and theme.js are consistent and the module is off by default', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const ext = JSON.parse(read('custom/extensions/ocs_ajax_cart/extension.json'));
  const mig = read('custom/extensions/ocs_ajax_cart/db/001_install.php');
  assert.equal(ext.spec, 'SPEC-026');
  for (const e of ext.events) assert.ok(mig.includes(`'${e}'`), `migration does not register ${e}`);
  assert.match(mig, /'module_ocs_ajax_cart_status' => 0/);
  assert.match(mig, /event->register\(\$code, \$trigger, '[^']+' \. \$method, 3\)/, 'labels must run after ocs_listing (sort 1)');

  const ctl = read('custom/extensions/ocs_ajax_cart/upload/catalog/controller/extension/module/ocs_ajax_cart.php');
  for (const m of ['public function panel', 'public function update', 'public function remove']) assert.ok(ctl.includes(m), `controller lacks ${m}`);
  assert.doesNotMatch(ctl, /post\['(price|total)'\]/, 'prices are never read from the request');
  assert.match(ctl, /hasLine\(/, 'only lines of the visitor cart can be changed');

  const tpl = read('custom/extensions/ocs_ajax_cart/upload/catalog/view/theme/default/template/extension/module/ocs_ajax_cart.twig');
  const js = read('custom/theme/ocs/javascript/theme.js');
  for (const cls of ['ocs-cart-line', 'ocs-cart-minus', 'ocs-cart-plus', 'ocs-cart-remove', 'ocs-cart-qty', 'ocs-cart-continue', 'data-key', 'data-close']) {
    assert.ok(tpl.includes(cls), `panel template lacks ${cls}`);
    assert.ok(js.includes(cls), `theme.js does not use ${cls}`);
  }
  assert.ok(js.includes("data-ocs-cart") && js.includes('checkout/cart/add') && js.includes("variant: 'side'"));
  assert.match(js, /base = document\.body\.getAttribute\('data-ocs-cart'\)/, 'body attribute is read after the DOM exists');
  assert.match(read('custom/theme/ocs/template/common/header.twig'), /<body\{% if ocs_cart %\} data-ocs-cart="\{\{ ocs_cart\.url \}\}"\{% endif %\}/);
  assert.match(read('custom/theme/ocs/template/ocs/card.twig'), /product\.ocs_stock/);
  assert.match(read('custom/theme/ocs/template/product/product.twig'), /ocs_stock\.label/);

  const keys = (l, p) => [...read(`custom/extensions/ocs_ajax_cart/upload/${p}/language/${l}/extension/module/ocs_ajax_cart.php`).matchAll(/\$_\['([a-z_]+)'\]/g)].map((m) => m[1]).sort();
  assert.deepEqual(keys('en-gb', 'catalog'), keys('uk-ua', 'catalog'));
  assert.deepEqual(keys('ru-ru', 'catalog'), keys('uk-ua', 'catalog'));
  assert.deepEqual(keys('en-gb', 'admin'), keys('uk-ua', 'admin'));
  for (const k of ['text_in_stock', 'text_few_left', 'text_out_of_stock', 'error_stock', 'error_minimum']) assert.ok(keys('uk-ua', 'catalog').includes(k), `missing ${k}`);
});

// --- SPEC-028 AJAX search ---
// TEST-SRC-001..004 - verifies REQ-SRC-001..006, 010, 011, 021 (manifest, endpoint safety, markup hooks used by theme.js, off by default)
test('ajax search: manifest, endpoint, template hooks and theme.js are consistent', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const ext = JSON.parse(read('custom/extensions/ocs_ajax_search/extension.json'));
  const mig = read('custom/extensions/ocs_ajax_search/db/001_install.php');
  assert.equal(ext.spec, 'SPEC-028');
  for (const e of ext.events) assert.ok(mig.includes(`'${e}'`), `migration does not register ${e}`);
  assert.match(mig, /'module_ocs_ajax_search_status' => 0/);

  const ctl = read('custom/extensions/ocs_ajax_search/upload/catalog/controller/extension/module/ocs_ajax_search.php');
  assert.match(ctl, /rateLimit\(.*time\(\), 10, 10\)/, 'REQ-SRC-011: 10 requests per 10 s');
  assert.match(ctl, /mb_strlen\(\$q, 'UTF-8'\) > 100/, 'REQ-SRC-011: query length limit');
  assert.match(ctl, /HTTP\/1\.1 429/);
  assert.equal((ctl.match(/\$this->url->link\(/g) || []).length, 1, 'JSON must use plainLink (core url->link returns &amp;); only the wrapper calls it');
  assert.match(ctl, /html_entity_decode\(\$product\['name'\]/, 'names are decoded to plain text for textContent');
  assert.match(ctl, /getProducts\(array\('filter_name'/, 'products come from the core filter (store, status, price rules)');

  const twig = read('custom/theme/ocs/template/common/search.twig');
  assert.ok(twig.startsWith('{# @core-file catalog/view/theme/default/template/common/search.twig @core-version 3.0.3.7 #}'));
  for (const hook of ['id="search"', 'name="search"', 'input-group-btn', 'data-ocs-search', 'data-strings', 'ocs-search-dd', 'role="combobox"']) assert.ok(twig.includes(hook), `search.twig lacks ${hook}`);
  assert.match(twig, /\{% if ocs_search %\}/, 'falls back to core markup when the module is off');

  const js = read('custom/theme/ocs/javascript/theme.js');
  const from = js.indexOf('AJAX search (SPEC-028)');
  const ends = ['Product gallery on two linked', 'Quick view (SPEC-037', 'Option-level stock (SPEC-037', 'City selector (SPEC-032'].map((m) => js.indexOf(m)).filter((i) => i > from);
  const block = js.slice(from, ends.length ? Math.min(...ends) : undefined);
  for (const s of ['ocs.search.recent', "'ArrowDown'", "'Escape'", "'Enter'", 'textContent', 'ocs-search-clear', 'encodeURIComponent']) assert.ok(block.includes(s), `search JS lacks ${s}`);
  assert.doesNotMatch(block.replace(/dd\.innerHTML = '';/g, ''), /\.innerHTML\s*=/, 'server data is never written with innerHTML');

  const keys = (l, p) => [...read(`custom/extensions/ocs_ajax_search/upload/${p}/language/${l}/extension/module/ocs_ajax_search.php`).matchAll(/\$_\['([a-z_]+)'\]/g)].map((m) => m[1]).sort();
  assert.deepEqual(keys('en-gb', 'catalog'), keys('uk-ua', 'catalog'));
  assert.deepEqual(keys('ru-ru', 'catalog'), keys('uk-ua', 'catalog'));
  assert.deepEqual(keys('en-gb', 'admin'), keys('uk-ua', 'admin'));
  const events = read('custom/extensions/ocs_ajax_search/upload/catalog/controller/extension/ocs_ajax_search/events.php');
  for (const k of [...events.matchAll(/'(recent|popular|clear|all|empty|error|products|categories|brands)'/g)].map((m) => m[1])) assert.ok(keys('uk-ua', 'catalog').includes(`text_${k}`), `missing text_${k}`);
});

// --- SPEC-029 stickers ---
// TEST-STK-001, 003, 004 - verifies REQ-STK-003, REQ-STK-006, REQ-EXT-051 (manifest vs migration, OCMOD anchors unique in core, templates, language parity, escaping)
test('stickers: manifest, OCMOD anchors, template slots and escaping are consistent', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const ext = JSON.parse(read('custom/extensions/ocs_stickers/extension.json'));
  const mig = read('custom/extensions/ocs_stickers/db/001_install.php');
  assert.equal(ext.spec, 'SPEC-029');
  for (const e of ext.events) assert.ok(mig.includes(`'${e}'`), `migration does not register ${e}`);
  for (const t of ext.tables) assert.ok(mig.includes(t.replace(/^oc_/, '{{PREFIX}}')), `table ${t} not created`);
  assert.match(mig, /'module_ocs_stickers_status' => 0/);

  const core = read('core/upload/admin/view/template/catalog/product_form.twig').replace(/\r/g, '');
  const ocmod = read('custom/ocmod/ocs_stickers_product_form.ocmod.xml');
  const anchors = [...ocmod.matchAll(/<search><!\[CDATA\[(.*?)\]\]><\/search>/g)].map((m) => m[1]);
  assert.equal(anchors.length, 2);
  for (const a of anchors) assert.equal(core.split(a).length - 1, 1, `OCMOD anchor must be unique in core: ${a}`);
  assert.match(ocmod, /@core-file admin\/view\/template\/catalog\/product_form\.twig/);
  for (const v of ['ocs_stickers_tab_link', 'ocs_stickers_tab_pane']) assert.ok(ocmod.includes(`{{ ${v} }}`));
  const ev = read('custom/extensions/ocs_stickers/upload/admin/controller/extension/ocs_stickers/events.php');
  for (const v of ['ocs_stickers_tab_link', 'ocs_stickers_tab_pane', 'ocs_stickers_present', 'ocs_sticker_ids']) assert.ok(ev.includes(v), `admin events lack ${v}`);
  assert.ok(read('custom/extensions/ocs_stickers/upload/admin/view/template/extension/ocs_stickers/tab.twig').includes('ocs_stickers_present'));

  const card = read('custom/theme/ocs/template/ocs/card.twig');
  const prod = read('custom/theme/ocs/template/product/product.twig');
  for (const pos of ['tl', 'tr', 'bl']) {
    assert.ok(card.includes(`product.stickers.${pos}`), `card lacks ${pos}`);
    assert.ok(prod.includes(`ocs_stickers.${pos}`), `product page lacks ${pos}`);
  }
  assert.match(card, /--badge-bg: \{\{ s\.bg \}\}; --badge-fg: \{\{ s\.fg \}\}/);
  assert.match(card, /product\.discount_pct and not product\.stickers/, 'built-in discount badge steps aside when stickers are shown');

  const catalogEv = read('custom/extensions/ocs_stickers/upload/catalog/model/extension/ocs_stickers/stickers.php');
  assert.match(catalogEv, /htmlspecialchars\(\$sticker\['text'\]/, 'sticker text is escaped before the unescaped Twig output');
  const model = read('custom/extensions/ocs_stickers/upload/catalog/model/extension/ocs_stickers/stickers.php');
  assert.equal((model.match(/->query\(/g) || []).length, 2, 'definitions (cached) + one batched facts query');
  assert.match(model, /cache->set\(/);

  const keys = (p) => [...read(p).matchAll(/\$_\['([a-z_]+)'\]/g)].map((m) => m[1]).sort();
  const adm = (l) => keys(`custom/extensions/ocs_stickers/upload/admin/language/${l}/extension/module/ocs_stickers.php`);
  assert.deepEqual(adm('en-gb'), adm('uk-ua'));
  const tab = (l) => keys(`custom/extensions/ocs_stickers/upload/admin/language/${l}/extension/ocs_stickers/tab.php`);
  assert.deepEqual(tab('en-gb'), tab('uk-ua'));
  assert.ok(tab('uk-ua').every((k) => k.startsWith('ocs_')), 'tab strings are prefixed (the file is loaded while another form renders)');
  const ctl = read('custom/extensions/ocs_stickers/upload/admin/controller/extension/module/ocs_stickers.php');
  const coreAdminKeys = ['text_home', 'text_enabled', 'text_disabled']; // defined by core's admin default language file
  for (const k of [...ctl.matchAll(/language->get\('((?:error|heading|text)_[a-z_]+)'\)/g)].map((m) => m[1]).filter((k) => !coreAdminKeys.includes(k))) assert.ok(adm('uk-ua').includes(k), `missing admin string ${k}`);
});

// --- SPEC-030 recently viewed ---
// TEST-VWD-001..003 - verifies REQ-VWD-002, 003, 010, 011 (manifest, cookie flags, card partial reuse, one history model, language parity)
test('recently viewed: manifest, cookie flags, shared card partial and strings are consistent', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const ext = JSON.parse(read('custom/extensions/ocs_viewed/extension.json'));
  const mig = read('custom/extensions/ocs_viewed/db/001_install.php');
  assert.equal(ext.spec, 'SPEC-030');
  for (const e of ext.events) assert.ok(mig.includes(`'${e}'`), `migration does not register ${e}`);
  for (const t of ext.tables) assert.ok(mig.includes(t.replace(/^oc_/, '{{PREFIX}}')), `table ${t} not created`);
  assert.match(mig, /'module_ocs_viewed_status' => 0/);

  const model = read('custom/extensions/ocs_viewed/upload/catalog/model/extension/ocs_viewed/history.php');
  for (const flag of ["'httponly' => true", "'samesite' => 'Lax'", "'path'     => '/'"]) assert.ok(model.includes(flag), `cookie lacks ${flag}`);
  assert.match(model, /Ocs\\Viewed::MAX/, 'history is capped at 20');
  assert.match(model, /ocs_viewed_merged/, 'browser list is merged once per session');

  const ev = read('custom/extensions/ocs_viewed/upload/catalog/controller/extension/ocs_viewed/events.php');
  assert.match(ev, /getProduct\(\$product_id\)/, 'only existing, visible products are recorded');

  const ctl = read('custom/extensions/ocs_viewed/upload/catalog/controller/extension/module/ocs_viewed.php');
  assert.match(ctl, /isset\(\$this->request->get\['product_id'\]\)/, 'current product is excluded');
  assert.match(ctl, /if \(!\$product\) \{\s*continue;/, 'disabled or deleted products are skipped');
  const tpl = read('custom/extensions/ocs_viewed/upload/catalog/view/theme/default/template/extension/module/ocs_viewed.twig');
  assert.ok(tpl.includes("{% include 'ocs/template/ocs/card.twig' %}"), 'same card markup as the listings');
  assert.match(read('custom/theme/ocs/stylesheet/stylesheet.css'), /\.ocs-viewed-grid/);

  const keys = (p) => [...read(p).matchAll(/\$_\['([a-z_]+)'\]/g)].map((m) => m[1]).sort();
  const cat = (l) => keys(`custom/extensions/ocs_viewed/upload/catalog/language/${l}/extension/module/ocs_viewed.php`);
  assert.deepEqual(cat('en-gb'), cat('uk-ua'));
  assert.deepEqual(cat('ru-ru'), cat('uk-ua'));
  const adm = (l) => keys(`custom/extensions/ocs_viewed/upload/admin/language/${l}/extension/module/ocs_viewed.php`);
  assert.deepEqual(adm('en-gb'), adm('uk-ua'));
});

const HOME_EXT = ['ocs_home_slider', 'ocs_brand_strip', 'ocs_product_carousel', 'ocs_newsletter', 'ocs_seo_text'];

// TEST-HOM-002 - verifies REQ-HOM-001, REQ-HOM-010, REQ-HOM-020, REQ-HOM-030, REQ-HOM-040, REQ-HOM-051 (manifests match migrations; off by default; strings identical across languages)
test('home block extensions: manifests match migrations, are off by default and strings are identical', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  for (const code of HOME_EXT) {
    const ext = JSON.parse(read(`custom/extensions/${code}/extension.json`));
    assert.equal(ext.code, code);
    assert.equal(ext.spec, 'SPEC-033');
    const mig = read(`custom/extensions/${code}/db/001_install.php`);
    assert.ok(mig.includes(`register('module', '${code}')`), `${code}: not registered`);
    for (const t of ext.tables) assert.ok(mig.includes(`{{PREFIX}}${t.replace(/^oc_/, "")}`), `${code}: table ${t}`);
    for (const s of ext.settings.filter((k) => k.endsWith('_status'))) assert.match(mig, new RegExp(`'${s}'(\\s*=>\\s*|,\\s*)0`), `${code}: ${s} must default to 0`);
    const keys = (side, l) => [...read(`custom/extensions/${code}/upload/${side}/language/${l}/extension/module/${code}.php`).matchAll(/\$_\['([a-z_]+)'\]/g)].map((m) => m[1]).sort();
    for (const l of ['en-gb', 'ru-ru']) assert.deepEqual(keys('catalog', l), keys('catalog', 'uk-ua'), `${code} catalog strings ${l}`);
    assert.deepEqual(keys('admin', 'en-gb'), keys('admin', 'uk-ua'), `${code} admin strings`);
  }
});

// TEST-HOM-001 - verifies REQ-HOM-050, REQ-HOM-051 (carousel hooks used by the blocks exist in theme.js and css; controls are labelled)
test('home blocks: carousel markup matches theme.js and css; controls have labels', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const js = read('custom/theme/ocs/javascript/theme.js');
  assert.match(js, /OCS\.carousel\s*=/);
  assert.match(js, /prefers-reduced-motion/);
  for (const code of ['ocs_home_slider', 'ocs_brand_strip', 'ocs_product_carousel']) {
    const twig = read(`custom/extensions/${code}/upload/catalog/view/theme/default/template/extension/module/${code}.twig`);
    assert.match(twig, /ocs-carousel-prev" aria-label="\{\{ text_prev \}\}"/, `${code} prev label`);
    assert.match(twig, /ocs-carousel-next" aria-label="\{\{ text_next \}\}"/, `${code} next label`);
    assert.match(twig, /ocs-carousel-track/);
  }
  for (const sel of ['.ocs-hero', '.ocs-brands', '.ocs-pcarousel', '.ocs-news', '.ocs-seo-home']) assert.ok(css.includes(sel), `css misses ${sel}`);
});

// TEST-HOM-002 - verifies REQ-HOM-001, REQ-HOM-050 (first slide is the LCP image: explicit size and high priority; others lazy; one cached query)
test('hero slider: LCP attributes, lazy rest, cached query', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const twig = read('custom/extensions/ocs_home_slider/upload/catalog/view/theme/default/template/extension/module/ocs_home_slider.twig');
  assert.match(twig, /fetchpriority/);
  assert.match(twig, /loading="lazy"/);
  assert.match(twig, /width="/);
  assert.match(read('custom/extensions/ocs_home_slider/upload/catalog/controller/extension/module/ocs_home_slider.php'), /cache->get/);
});

// TEST-HOM-005 - verifies REQ-HOM-030 (newsletter: token, rate limit, honeypot, consent; the core customer flag only for the signed-in owner of the address)
test('newsletter endpoint: token, rate limit, honeypot, consent and signed-in-only customer flag', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const ctl = read('custom/extensions/ocs_newsletter/upload/catalog/controller/extension/module/ocs_newsletter.php');
  assert.match(ctl, /tokenValid/);
  assert.match(ctl, /allow\('newsletter'\)/);
  assert.match(ctl, /\$post\['website'\]/);
  assert.match(ctl, /\$post\['agree'\]/);
  assert.match(ctl, /\$own = \$this->customer->isLogged\(\) && strtolower\(\$this->customer->getEmail\(\)\) === \$email/);
  assert.match(ctl, /X-Robots-Tag: noindex/);
  const twig = read('custom/extensions/ocs_newsletter/upload/catalog/view/theme/default/template/extension/module/ocs_newsletter.twig');
  assert.match(twig, /class="ocs-news-form"/);
  assert.match(twig, /name="website"/);
  assert.match(twig, /name="agree"/);
  assert.match(twig, /aria-live="polite"/);
  assert.match(read('custom/theme/ocs/javascript/theme.js'), /\.ocs-news-form/);
});

// TEST-HOM-004 - verifies REQ-HOM-020 (instances are validated; ids are cast to integers; all four modes exist)
test('product carousel: admin validation and id sources', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const adm = read('custom/extensions/ocs_product_carousel/upload/admin/controller/extension/module/ocs_product_carousel.php');
  assert.match(adm, /addModule\('ocs_product_carousel'/);
  assert.match(adm, /editModule/);
  assert.match(adm, /in_array\(\$post\['mode'\]/);
  const ctl = read('custom/extensions/ocs_product_carousel/upload/catalog/controller/extension/module/ocs_product_carousel.php');
  assert.match(ctl, /array_map\('intval'/);
  assert.match(ctl, /\$category_id = \(int\)/);
  for (const mode of ['newest', 'bestsellers', 'discounted', 'manual']) assert.ok(ctl.includes(mode), mode);
});

// TEST-PRD-006 - verifies REQ-PRD-041, REQ-EXT-051 (manifest matches the migration; strings identical across languages; off by default)
test('product extras: manifest matches migration and language files have identical keys', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const code = 'ocs_product_extras';
  const ext = JSON.parse(read(`custom/extensions/${code}/extension.json`));
  assert.equal(ext.spec, 'SPEC-035');
  const mig = read(`custom/extensions/${code}/db/001_install.php`);
  for (const e of ext.events) assert.ok(mig.includes(`'${e}'`), `event ${e}`);
  for (const t of ext.tables) assert.ok(mig.includes(`{{PREFIX}}${t.replace(/^oc_/, '')}`), `table ${t}`);
  for (const s of ext.settings) assert.ok(mig.includes(`'${s}'`), `setting ${s}`);
  assert.match(mig, /'module_ocs_product_extras_status' => 0/);
  const keys = (side, l, f) => [...read(`custom/extensions/${code}/upload/${side}/language/${l}/extension/${f}.php`).matchAll(/\$_\['([a-z_]+)'\]/g)].map((m) => m[1]).sort();
  for (const l of ['en-gb', 'ru-ru']) assert.deepEqual(keys('catalog', l, `module/${code}`), keys('catalog', 'uk-ua', `module/${code}`), `catalog ${l}`);
  assert.deepEqual(keys('admin', 'en-gb', `module/${code}`), keys('admin', 'uk-ua', `module/${code}`));
  assert.deepEqual(keys('admin', 'en-gb', `${code}/tab`), keys('admin', 'uk-ua', `${code}/tab`));
});

// TEST-PRD-005 - verifies REQ-PRD-001, REQ-PRD-031, REQ-THM-013 (the override keeps the core hooks and adds the section bar, tabs and carousels)
test('product override keeps core hooks and adds section bar, questions tab and carousels', () => {
  const twig = readFileSync(join(REPO_ROOT, 'custom/theme/ocs/template/product/product.twig'), 'utf8');
  for (const hook of ['id="button-cart"', 'id="product"', 'id="input-quantity"', 'name="product_id"', 'id="tab-description"', 'id="tab-review"', 'id="button-review"', 'wishlist.add', 'compare.add', 'class="thumbnails"']) assert.ok(twig.includes(hook), hook);
  for (const el of ['id="ocs-sections"', 'id="tab-questions"', "'id': 'ocs-accessories'", "'id': 'ocs-similar'", 'ocs-buy-slot', 'application/ld+json', 'fetchpriority="high"']) assert.ok(twig.includes(el), el);
  const js = readFileSync(join(REPO_ROOT, 'custom/theme/ocs/javascript/theme.js'), 'utf8');
  assert.match(js, /getElementById\('ocs-sections'\)/);
  assert.match(js, /IntersectionObserver/);
  assert.ok(css.includes('.ocs-sections'));
});

// TEST-PRD-003 - verifies REQ-PRD-010 (questions: token, rate limit, honeypot, moderation by default, raw storage and single escaping)
test('questions: guarded endpoint, moderated by default, escaped once on output', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const ctl = read('custom/extensions/ocs_product_extras/upload/catalog/controller/extension/module/ocs_product_extras.php');
  assert.match(ctl, /tokenValid/);
  assert.match(ctl, /allow\('question'\)/);
  assert.match(ctl, /\$post\['website'\]/);
  assert.match(ctl, /html_entity_decode/);
  const model = read('custom/extensions/ocs_product_extras/upload/catalog/model/extension/ocs_product_extras/extras.php');
  assert.match(model, /status = '0'/);
  assert.match(model, /status = '1'/);
  const adm = read('custom/extensions/ocs_product_extras/upload/admin/controller/extension/module/ocs_product_extras.php');
  assert.match(adm, /htmlspecialchars\(\$row\['text'\]/);
  assert.match(adm, /htmlspecialchars\(\$row\['answer'\]/);
});

// TEST-CTY-001 - verifies REQ-CTY-041, REQ-EXT-051 (manifest matches the migration; strings identical across languages; off by default)
test('city extension: manifest matches migration and language files have identical keys', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const code = 'ocs_city';
  const ext = JSON.parse(read(`custom/extensions/${code}/extension.json`));
  assert.equal(ext.spec, 'SPEC-032');
  const mig = read(`custom/extensions/${code}/db/001_install.php`);
  for (const e of ext.events) assert.ok(mig.includes(`'${e}'`), `event ${e}`);
  for (const t of ext.tables) assert.ok(mig.includes(`{{PREFIX}}${t.replace(/^oc_/, '')} (`), `table ${t}`);
  for (const s of ext.settings) assert.ok(mig.includes(`'${s}'`), `setting ${s}`);
  assert.match(mig, /'module_ocs_city_status' => 0/);
  const keys = (side, l) => [...read(`custom/extensions/${code}/upload/${side}/language/${l}/extension/module/${code}.php`).matchAll(/\$_\['([a-z_]+)'\]/g)].map((m) => m[1]).sort();
  for (const l of ['en-gb', 'ru-ru']) assert.deepEqual(keys('catalog', l), keys('catalog', 'uk-ua'), `catalog ${l}`);
  assert.deepEqual(keys('admin', 'en-gb'), keys('admin', 'uk-ua'), 'admin strings');
});

// TEST-CTY-002 - verifies REQ-CTY-002, REQ-CTY-011 (header prompt, popup hooks, delivery slot and theme.js/css selectors agree)
test('city selector: header, product slot, theme.js and css hooks are consistent', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const header = read('custom/theme/ocs/template/common/header.twig');
  for (const hook of ['id="ocs-city-prompt"', 'data-ocs-city-yes', 'data-ocs-city-open', 'ocs-city-top']) assert.ok(header.includes(hook), hook);
  assert.ok(read('custom/theme/ocs/template/product/product.twig').includes('id="ocs-delivery-wrap"'));
  const js = read('custom/theme/ocs/javascript/theme.js');
  for (const hook of ['ocs-delivery-wrap', 'ocs-city-choice', 'ocs-city-search', 'ocs_city_ok']) assert.ok(js.includes(hook), hook);
  const select = read('custom/extensions/ocs_city/upload/catalog/view/theme/default/template/extension/module/ocs_city_select.twig');
  for (const hook of ['ocs-city-choice', 'ocs-city-search', 'ocs-city-title', 'data-close']) assert.ok(select.includes(hook), hook);
  for (const sel of ['.ocs-city-btn', '.ocs-city-prompt', '.ocs-delivery', '.ocs-store', '.ocs-track-form']) assert.ok(css.includes(sel), sel);
});

// TEST-CTY-006 - verifies REQ-CTY-030, REQ-CTY-041 (tracking: token, rate limit, one generic answer, no staff comments)
test('order tracking: guarded, generic mismatch answer, no comments in the history query', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const ctl = read('custom/extensions/ocs_city/upload/catalog/controller/extension/module/ocs_city.php');
  assert.match(ctl, /tokenValid/);
  assert.match(ctl, /allow\('track'\)/);
  assert.match(ctl, /trackingMatches/);
  assert.equal((ctl.match(/track_not_found/g) || []).length, 1, 'one message for every mismatch');
  const model = read('custom/extensions/ocs_city/upload/catalog/model/extension/ocs_city/city.php');
  assert.ok(!/oh\.comment|\.comment/.test(model), 'comments of the staff are never selected');
  assert.match(model, /order_status_id > '0'/);
});

// TEST-CRD-004 - verifies REQ-CRD-021, REQ-EXT-051 (manifests match migrations; strings identical across languages; both blocks off by default)
test('credit and trade-in: manifests match migrations, strings are identical, off by default', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  for (const code of ['ocs_credit', 'ocs_tradein']) {
    const ext = JSON.parse(read(`custom/extensions/${code}/extension.json`));
    assert.equal(ext.spec, 'SPEC-036');
    const mig = read(`custom/extensions/${code}/db/001_install.php`);
    for (const e of ext.events) assert.ok(mig.includes(`'${e}'`), `event ${e}`);
    for (const t of ext.tables) assert.ok(mig.includes(`{{PREFIX}}${t.replace(/^oc_/, '')} (`), `table ${t}`);
    for (const s of ext.settings) assert.ok(mig.includes(`'${s}'`), `setting ${s}`);
    assert.match(mig, new RegExp(`'module_${code}_status' => 0`));
    const keys = (side, l) => [...read(`custom/extensions/${code}/upload/${side}/language/${l}/extension/module/${code}.php`).matchAll(/\$_\['([a-z_]+)'\]/g)].map((m) => m[1]).sort();
    for (const l of ['en-gb', 'ru-ru']) assert.deepEqual(keys('catalog', l), keys('catalog', 'uk-ua'), `${code} catalog ${l}`);
    assert.deepEqual(keys('admin', 'en-gb'), keys('admin', 'uk-ua'), `${code} admin strings`);
  }
});

// TEST-CRD-001 - verifies REQ-CRD-001, REQ-CRD-002, REQ-CRD-003 (block markup, disclaimer, JS formula and quick-order hand-off agree)
test('instalment block: hooks, disclaimer and hand-off are consistent', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const twig = read('custom/extensions/ocs_credit/upload/catalog/view/theme/default/template/extension/module/ocs_credit.twig');
  for (const hook of ['data-ocs-credit="{{ config_json }}"', 'ocs-credit-disclaimer', 'name="ocs-credit-plan"', 'ocs-credit-pay', 'data-ocs-credit-details', 'ocs-credit-terms']) assert.ok(twig.includes(hook), hook);
  assert.match(twig, /\{% if not any %\} hidden\{% endif %\}/, 'the block hides itself when no plan fits');
  const ev = read('custom/extensions/ocs_credit/upload/catalog/controller/extension/ocs_credit/events.php');
  assert.match(ev, /text_disclaimer/, 'the disclaimer always has a default text');
  assert.match(ev, /htmlspecialchars\(json_encode\(/, 'the JSON of the attribute is entity-encoded as a whole');
  const js = read('custom/theme/ocs/javascript/theme.js');
  assert.match(js, /amount \* r \/ \(1 - Math\.pow\(1 \+ r, -months\)\)/, 'same annuity formula as Ocs\\Credit::payment');
  assert.match(js, /ocs-quick-btn'\) && OCS\.credit && OCS\.credit\.note/);
  assert.ok(css.includes('.ocs-credit'));
});

// TEST-CRD-003 - verifies REQ-CRD-020 (trade-in: token, rate limit, honeypot, uploads outside the docroot, photos served only through the admin)
test('trade-in: guarded endpoint, storage outside the docroot, admin-only photo access', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const ctl = read('custom/extensions/ocs_tradein/upload/catalog/controller/extension/module/ocs_tradein.php');
  assert.match(ctl, /tokenValid/);
  assert.match(ctl, /allow\('tradein'\)/);
  assert.match(ctl, /DIR_STORAGE/);
  assert.match(ctl, /Tradein::check/);
  assert.match(ctl, /move_uploaded_file/);
  const form = read('custom/extensions/ocs_tradein/upload/catalog/view/theme/default/template/extension/module/ocs_tradein_form.twig');
  for (const hook of ['enctype="multipart/form-data"', 'class="ocs-req-form"', 'name="photos[]"', 'name="website"', 'data-error-for="photos"']) assert.ok(form.includes(hook), hook);
  const adm = read('custom/extensions/ocs_tradein/upload/admin/controller/extension/module/ocs_tradein.php');
  assert.match(adm, /hasPermission\('access'/);
  assert.match(adm, /validStoredName/);
  assert.match(adm, /nosniff/);
  assert.match(adm, /photoExists/);
  assert.match(read('custom/theme/ocs/template/product/product.twig'), /\{\{ ocs_credit_block \}\}[\s\S]*\{\{ ocs_tradein_banner \}\}/);
  assert.ok(read('custom/theme/ocs/template/product/category.twig').includes('{{ ocs_tradein_banner }}'));
});

// TEST-SWP-001 - verifies REQ-SWP-001, REQ-SWP-003, REQ-SWP-005, REQ-SWP-010 (carousels run on Swiper from the same markup; no second engine; Swiper loaded once)
test('swiper: carousels use core Swiper, the header loads it once, markup contract and fallback CSS remain', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const js = read('custom/theme/ocs/javascript/theme.js');
  assert.match(js, /new window\.Swiper\(root, options\)/);
  assert.match(js, /slidesPerView: single \? 1 : 'auto'/);
  assert.match(js, /autoplayDisableOnInteraction: false/);
  assert.match(js, /prefers-reduced-motion/);
  assert.match(js, /typeof window\.Swiper !== 'function'/, 'no Swiper = the plain scroller stays');
  assert.ok(!js.includes('scrollTo({left'), 'the hand-written scroll carousel is gone');
  assert.ok(!js.includes('data-playing'), 'the hand-written autoplay is gone');
  const header = read('custom/theme/ocs/template/common/header.twig');
  assert.match(header, /jquery\/swiper\/js\/swiper\.jquery\.min\.js/);
  assert.match(header, /'jquery\/swiper\/js\/swiper' not in script/, 'a core module cannot load Swiper a second time');
  assert.match(header, /'jquery\/swiper\/css\/swiper' not in style\.href/);
  for (const code of ['ocs_home_slider', 'ocs_brand_strip', 'ocs_product_carousel']) {
    const twig = read(`custom/extensions/${code}/upload/catalog/view/theme/default/template/extension/module/${code}.twig`);
    assert.match(twig, /ocs-carousel-track/);
    assert.match(twig, /ocs-carousel-item/);
  }
  assert.ok(css.includes('.ocs-carousel.swiper-container .ocs-carousel-track'));
  assert.ok(css.includes('scroll-snap-type: x mandatory'), 'fallback scroller without Swiper');
});

// TEST-SWP-002 - verifies REQ-SWP-004 (gallery: linked Swipers, core hooks kept, explicit size and priority on the first image)
test('product gallery: linked swipers keep the core zoom hooks and the LCP attributes', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const twig = read('custom/theme/ocs/template/product/product.twig');
  for (const hook of ['data-ocs-gallery="1"', 'ocs-gal-main swiper-container', 'ocs-gal-thumbs swiper-container', 'class="thumbnails swiper-wrapper"', 'class="thumbnail"', 'fetchpriority="high"']) assert.ok(twig.includes(hook), hook);
  assert.ok(twig.includes('<ul class="thumbnails">'), 'the core markup is the fallback when the extras are off');
  const js = read('custom/theme/ocs/javascript/theme.js');
  assert.match(js, /querySelector\('\[data-ocs-gallery\]'\)/);
  assert.match(js, /main\.slideTo\(n\)/);
  assert.ok(twig.includes("$('.thumbnails').magnificPopup"), 'the zoom popup is still bound to the core hook');
  const ev = read('custom/extensions/ocs_product_extras/upload/catalog/controller/extension/ocs_product_extras/events.php');
  assert.match(ev, /getProductImages/);
  assert.ok(css.includes('.ocs-gal-thumbs'));
});

// TEST-QVW-001, TEST-SWP-003 - verifies REQ-QVW-001..004, REQ-SWP-011, REQ-SWP-012 (quick view: manifest, endpoint, buttons, shared options partial, no caching)
test('quick view: manifest, endpoint, shared options and theme hooks are consistent and the module is off by default', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const code = 'ocs_quick_view';
  const ext = JSON.parse(read(`custom/extensions/${code}/extension.json`));
  assert.equal(ext.spec, 'SPEC-037');
  const mig = read(`custom/extensions/${code}/db/001_install.php`);
  for (const e of ext.events) assert.ok(mig.includes(`'${e}'`), `event ${e}`);
  assert.match(mig, /'module_ocs_quick_view_status', 0/);
  const keys = (side, l) => [...read(`custom/extensions/${code}/upload/${side}/language/${l}/extension/module/${code}.php`).matchAll(/\$_\['([a-z_]+)'\]/g)].map((m) => m[1]).sort();
  for (const l of ['en-gb', 'ru-ru']) assert.deepEqual(keys('catalog', l), keys('catalog', 'uk-ua'), `catalog ${l}`);
  assert.deepEqual(keys('admin', 'en-gb'), keys('admin', 'uk-ua'), 'admin strings');
  const ctl = read(`custom/extensions/${code}/upload/catalog/controller/extension/module/${code}.php`);
  assert.match(ctl, /Cache-Control: no-store/);
  assert.match(ctl, /module_ocs_quick_view_status/);
  assert.match(ctl, /ocs_request\/options/, 'options come from the shared builder');
  const twig = read(`custom/extensions/${code}/upload/catalog/view/theme/default/template/extension/module/${code}.twig`);
  for (const hook of ['data-ocs-product="1"', "include 'ocs/template/ocs/options.twig'", 'ocs-qv-add', 'ocs-quick-btn', 'ocs-preorder-btn', 'ocs-subscribe-btn', 'ocs-qv-slides swiper-container']) assert.ok(twig.includes(hook), hook);
  assert.ok(read('custom/theme/ocs/template/product/product.twig').includes("include 'ocs/template/ocs/options.twig'"), 'the product page uses the same partial');
  const js = read('custom/theme/ocs/javascript/theme.js');
  for (const hook of ['data-ocs-qv', 'ocs-qv-btn', 'OCS.qv = {open: open}', "closest('[data-ocs-product]')", 'ocs-qv-add']) assert.ok(js.includes(hook), hook);
  assert.match(read('custom/theme/ocs/template/common/header.twig'), /data-ocs-qv="\{\{ ocs_qv\.url \}\}"/);
});

// TEST-STK-010 - verifies REQ-STK-010 (stickers in quick view and in the module blocks come from the one shared model method)
test('stickers: quick view and module blocks use forProducts, escaping happens once in the model', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const model = read('custom/extensions/ocs_stickers/upload/catalog/model/extension/ocs_stickers/stickers.php');
  assert.match(model, /public function forProducts\(\$ids\)/);
  assert.match(model, /htmlspecialchars\(\$sticker\['text'\]/);
  for (const p of [
    'custom/extensions/ocs_quick_view/upload/catalog/controller/extension/module/ocs_quick_view.php',
    'custom/extensions/ocs_product_carousel/upload/catalog/controller/extension/module/ocs_product_carousel.php',
    'custom/extensions/ocs_viewed/upload/catalog/controller/extension/module/ocs_viewed.php',
    'custom/extensions/ocs_product_extras/upload/catalog/model/extension/ocs_product_extras/extras.php',
    'custom/extensions/ocs_stickers/upload/catalog/controller/extension/ocs_stickers/events.php'
  ]) assert.match(read(p), /forProducts\(/, p);
  assert.ok(read('custom/extensions/ocs_quick_view/upload/catalog/view/theme/default/template/extension/module/ocs_quick_view.twig').includes('stickers.tl'));
});

// TEST-OPT-001 - verifies REQ-OPT-001 (out-of-stock values are flagged in the shared partial; the theme switches the buttons; endpoints use the same rule)
test('option-level stock: partial flags, JS switch and both endpoints use the shared rule', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const partial = read('custom/theme/ocs/template/ocs/options.twig');
  assert.ok((partial.match(/data-out="1"/g) || []).length >= 2, 'select and the input types are flagged');
  assert.ok(partial.includes('ocs_text_out'));
  const js = read('custom/theme/ocs/javascript/theme.js');
  assert.match(js, /option\[data-out\]:checked, input\[data-out\]:checked/);
  assert.ok(css.includes('.ocs-out-only[hidden]'));
  assert.match(read('custom/extensions/ocs_preorder/upload/catalog/controller/extension/module/ocs_preorder.php'), /Stock::preorderAppliesFor/);
  assert.match(read('custom/extensions/ocs_back_in_stock/upload/catalog/controller/extension/module/ocs_back_in_stock.php'), /Stock::selectionNeedsRequest/);
  assert.match(read('custom/extensions/ocs_request/upload/catalog/model/extension/ocs_request/options.php'), /optionOut/);
  const twig = read('custom/theme/ocs/template/product/product.twig');
  assert.ok(twig.includes('ocs_preorder_opt') && twig.includes('ocs_subscribe_opt'));
  const req = read('custom/theme/ocs/javascript/theme.js');
  assert.match(req, /snapshot\.forEach/, 'the product fields are read when the request popup opens, not at submit');
});

// TEST-OPT-002 - verifies REQ-OPT-002, REQ-OPT-003 (cron endpoint: key check, empty answer otherwise; at-most-once marking; admin shows option and date)
test('back in stock: cron endpoint is keyed, each subscription is marked before its mail', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const cron = read('custom/extensions/ocs_back_in_stock/upload/catalog/controller/extension/module/ocs_back_in_stock_cron.php');
  assert.match(cron, /BackInStock::keyValid/);
  assert.match(cron, /HTTP\/1\.1 404/);
  const lib = read('custom/extensions/ocs_back_in_stock/upload/system/library/ocs/backinstock.php');
  assert.ok(lib.indexOf("SET status = '1'") < lib.indexOf('$mail->send()'), 'marked first');
  assert.match(lib, /countAffected/, 'two overlapping runs cannot both send');
  assert.match(read('custom/extensions/ocs_back_in_stock/upload/admin/controller/extension/ocs_back_in_stock/events.php'), /BackInStock::run/);
  const adm = read('custom/extensions/ocs_back_in_stock/upload/admin/controller/extension/module/ocs_back_in_stock.php');
  assert.match(adm, /module_ocs_back_in_stock_cron_key'\] = /, 'saving the settings keeps the key');
  assert.match(adm, /optionNames/);
  assert.match(read('custom/extensions/ocs_back_in_stock/db/003_cron_key.php'), /random_bytes\(16\)/);
});

// TEST-LAY-001 - verifies REQ-LAY-001, REQ-LAY-002, REQ-LAY-010, REQ-LAY-012, REQ-LAY-021 (layers, Bootstrap and Swiper inside the lowest layer, fluid scale, shell grid, no new !important)
test('modern layout: cascade layers, layered vendor css, fluid scale and shell grid', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  assert.match(css, /@layer reset, tokens, legacy, base, layout, components, utilities, overrides;/);
  assert.match(css, /@import url\("\.\.\/\.\.\/\.\.\/javascript\/bootstrap\/css\/bootstrap\.min\.css"\) layer\(reset\);/);
  assert.match(css, /@import url\("\.\.\/\.\.\/\.\.\/javascript\/jquery\/swiper\/css\/swiper\.min\.css"\) layer\(reset\);/);
  assert.ok(css.indexOf('@import') < css.indexOf('@layer tokens'), '@import comes before any rule');
  const header = read('custom/theme/ocs/template/common/header.twig');
  assert.ok(!/<link[^>]*bootstrap\.min\.css/.test(header), 'Bootstrap CSS is only imported through the layer');
  assert.ok(!/<link[^>]*swiper\.min\.css/.test(header), 'Swiper CSS is only imported through the layer');
  assert.match(css, /html \{ font-size: 100%; \}/, 'rem is the browser default, not Bootstrap\'s 10px');
  for (const feature of ['clamp(', 'dvh', 'text-wrap: balance', 'color-mix(', 'aspect-ratio', 'inset-block-start', 'inline-size']) assert.ok(css.includes(feature), feature);
  assert.match(css, /body \{\s*display: grid; grid-template-columns: minmax\(0, 1fr\); grid-template-rows: auto auto 1fr auto;/);
  assert.equal((css.match(/!important\s*;/g) || []).length, 3, 'no new !important (the two reduced-motion declarations and the [hidden] guard)');
  assert.ok(header.includes('<div class="ocs-page">') && read('custom/theme/ocs/template/common/footer.twig').includes('</div>{# .ocs-page'), 'the page wrapper is opened by the header and closed by the footer');
});

// TEST-LAY-002 - verifies REQ-LAY-003 (header: one grid row, container queries, sticky blur; account icons moved next to the cart)
test('modern layout: header grid with container queries', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const header = read('custom/theme/ocs/template/common/header.twig');
  for (const hook of ['class="ocs-head"', 'ocs-head-grid', 'ocs-head-cat', 'ocs-head-search', 'ocs-head-actions', 'id="top-links"', 'ocs-head-cart', 'id="logo"']) assert.ok(header.includes(hook), hook);
  assert.ok(header.indexOf('<nav id="top"') < header.indexOf('<header class="ocs-head">'), 'utility strip above the main header');
  for (const rule of ['container: head / inline-size', '@container head (max-width: 62rem)', '@container head (max-width: 40rem)', 'grid-template-areas: "logo cat search actions"', 'backdrop-filter']) assert.ok(css.includes(rule), rule);
  assert.match(read('custom/theme/ocs/javascript/theme.js'), /setProperty\('--head-h'/);
});

// TEST-LAY-003 - verifies REQ-LAY-004, REQ-LAY-005, REQ-LAY-012 (card is a container; listing rows align through subgrid behind @supports)
test('modern layout: card container queries and subgrid listing', () => {
  for (const rule of ['.product-layout { container: card / inline-size;', '@container card (max-width: 12.5rem)', '@container card (min-width: 36rem)', '@supports (grid-template-rows: subgrid)', 'grid-template-rows: subgrid', 'grid-row: span 7', 'repeat(auto-fill, minmax(min(100%, var(--card-min)), 1fr))']) assert.ok(css.includes(rule), rule);
  const sub = css.slice(css.indexOf('@supports (grid-template-rows: subgrid)'));
  assert.ok(sub.indexOf('grid-template-rows: subgrid') > 0 && sub.includes('.product-thumb .ocs-buy-row { grid-row: 6; }'), 'rows are placed explicitly, so a missing rating cannot shift the price row');
});

// TEST-LAY-004 - verifies REQ-LAY-006 (home grid by container width, promo tiles, benefits strip module)
test('modern layout: home grid, promo tiles and the benefits module', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  for (const rule of ['#common-home #content { container: home / inline-size;', '@container home (min-width: 58rem)', '.ocs-hero-wrap.has-tiles', '.ocs-benefits', '@container hero (max-width: 44rem)']) assert.ok(css.includes(rule), rule);
  const slider = read('custom/extensions/ocs_home_slider/upload/catalog/view/theme/default/template/extension/module/ocs_home_slider.twig');
  assert.ok(slider.includes('ocs-hero-wrap') && slider.includes('ocs-hero-tiles'));
  assert.match(read('custom/extensions/ocs_home_slider/upload/catalog/controller/extension/module/ocs_home_slider.php'), /array_splice\(\$data\['slides'\], -2\)/);
  const code = 'ocs_benefits';
  const ext = JSON.parse(read(`custom/extensions/${code}/extension.json`));
  assert.equal(ext.spec, 'SPEC-038');
  assert.match(read(`custom/extensions/${code}/db/001_install.php`), /'module_ocs_benefits_status' => 0/);
  const keys = (side, l) => [...read(`custom/extensions/${code}/upload/${side}/language/${l}/extension/module/${code}.php`).matchAll(/\$_\['([a-z_]+)'\]/g)].map((m) => m[1]).sort();
  for (const l of ['en-gb', 'ru-ru']) assert.deepEqual(keys('catalog', l), keys('catalog', 'uk-ua'), `catalog ${l}`);
  assert.deepEqual(keys('admin', 'en-gb'), keys('admin', 'uk-ua'), 'admin strings');
  assert.match(read(`custom/extensions/${code}/upload/catalog/controller/extension/module/${code}.php`), /preg_match\('\/\^\[a-z0-9-\]\{1,32\}\$\/'/, 'the icon name is whitelisted');
});

// TEST-LAY-005 - verifies REQ-LAY-007 (sidebar grid through :has() and a page container; sticky toolbar)
test('modern layout: category grid with sidebar, :has() and sticky toolbar', () => {
  for (const rule of ['.ocs-page > .container { container: page / inline-size; }', ':has(> #content)', '@container page (min-width: 56rem)', ':has(> #column-left):has(> #column-right)', 'position: sticky; inset-block-start: var(--head-h); z-index: 30;', ':has(> div > .btn-group > #list-view)']) assert.ok(css.includes(rule), rule);
});

// TEST-LAY-006 - verifies REQ-LAY-008 (product grid: sticky gallery | info card, sections full width below, tabs keep the core ids)
test('modern layout: product page grid keeps the core hooks', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const twig = read('custom/theme/ocs/template/product/product.twig');
  assert.ok(twig.includes('class="row ocs-pdp"') && twig.includes('class="ocs-pdp-sections"'));
  assert.ok(twig.indexOf('class="ocs-pdp-sections"') > twig.indexOf('id="button-cart"'), 'the sections follow the grid');
  for (const hook of ['id="tab-description"', 'id="tab-review"', 'id="button-review"', 'id="product"', 'id="input-quantity"', 'class="nav nav-tabs"', 'data-toggle="tab"']) assert.ok(twig.includes(hook), hook);
  for (const rule of ['#product-product #content { container: pdpc / inline-size;', '@container pdpc (min-width: 52rem)', '.ocs-pdp-sections { container: sections / inline-size; }']) assert.ok(css.includes(rule), rule);
});

// TEST-LAY-007, TEST-LAY-008, TEST-LAY-009 - verifies REQ-LAY-009, REQ-LAY-011, REQ-LAY-021 (footer subgrid, dialogs as containers, stylesheet budget)
test('modern layout: footer subgrid, dialog containers and the stylesheet budget', () => {
  for (const rule of ['footer { container: foot / inline-size;', 'grid-template-columns: subgrid', '@container foot (max-width: 62rem)', '.ocs-modal-dialog { container: dialog / inline-size; }', '@container dialog (min-width: 44rem)', ':has(.ocs-qv)']) assert.ok(css.includes(rule), rule);
  assert.ok(Buffer.byteLength(css) <= 200 * 1024, 'stylesheet within 200 KB');
});

// TEST-VIS-001 - verifies REQ-VIS-001 (Inter is self-hosted: latin + cyrillic woff2 files, licence note, preload, swap, metric fallback)
test('visual identity: Inter is self-hosted with preload, swap and a metric-compatible fallback', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  for (const file of ['inter-latin.woff2', 'inter-cyrillic.woff2']) {
    const bytes = readFileSync(join(REPO_ROOT, 'custom/theme/ocs/fonts', file));
    assert.equal(bytes.subarray(0, 4).toString('latin1'), 'wOF2', file);
    assert.ok(bytes.length < 80 * 1024, file + ' stays small');
  }
  assert.match(read('custom/theme/ocs/fonts/README.txt'), /SIL Open Font License/);
  assert.match(css, /font-family: "Inter"; font-style: normal; font-weight: 400 800; font-display: swap;/);
  assert.match(css, /unicode-range: U\+0301, U\+0400-045F/);
  assert.match(css, /@font-face \{ font-family: "Inter Fallback"; src: local\("Arial"\)/);
  assert.match(css, /--font: "Inter", "Inter Fallback"/);
  const header = read('custom/theme/ocs/template/common/header.twig');
  assert.ok(header.includes('inter-latin.woff2') && header.includes('inter-cyrillic.woff2') && header.includes('rel="preload"'));
  assert.ok(!/fonts\.(googleapis|gstatic)\.com/.test(css + header), 'no third-party font request');
  assert.ok(css.includes('font-variant-numeric: tabular-nums'));
});

// TEST-VIS-002 - verifies REQ-VIS-007 (base components are restyled inside the components layer)
test('visual identity: Bootstrap components are re-skinned in the components layer', () => {
  const start = css.indexOf('/* ==== 36. SPEC-039 base components');
  const end = css.indexOf('/* ==== 37.');
  assert.ok(start > 0 && end > start);
  const block = css.slice(start, end);
  for (const sel of ['.btn {', '.btn-primary {', '.btn-default', '.form-control', '.dropdown-menu {', '.nav-tabs', '.breadcrumb {', '.pagination', '.panel {', '.table', '.alert {', '.tooltip-inner']) assert.ok(block.includes(sel), sel);
  assert.ok(block.includes('outline: 2px solid var(--focus)'), 'focus ring');
  assert.match(block, /box-shadow: 0 0 0 3px color-mix\(in srgb, var\(--accent\) 20%, transparent\)/);
});

// TEST-VIS-003 - verifies REQ-VIS-004 (card: tools overlay, code, round cart button, stock dot; hooks of the core JS kept)
test('visual identity: product card markup and styles', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const card = read('custom/theme/ocs/template/ocs/card.twig');
  for (const hook of ['ocs-card-tools', 'ocs-tool', 'class="ocs-code"', 'ocs-buy-row', 'ocs-cart-btn', 'ocs-dot', "wishlist.add('{{ product.product_id }}')", "compare.add('{{ product.product_id }}')", "cart.add('{{ product.product_id }}'", 'ocs-quick-btn', 'loading="lazy"']) assert.ok(card.includes(hook), hook);
  for (const rule of ['.ocs-tool {', '.ocs-cart-btn {', '.ocs-card-tools {', '@media (hover: none) { .ocs-tool', '@container card (max-width: 12.5rem)']) assert.ok(css.includes(rule), rule);
});

// TEST-VIS-004 - verifies REQ-VIS-002, REQ-VIS-008 (header: sprite, labelled actions with badges, core ids kept; counters mirrored by JS)
test('visual identity: header actions, sprite and counter mirror', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const header = read('custom/theme/ocs/template/common/header.twig');
  for (const hook of ["include 'ocs/template/ocs/sprite.twig'", 'class="ocs-head-actions" id="top-links"', 'id="wishlist-total"', 'id="compare-total"', 'ocs-head-cart', 'ocs-city-top', 'ocs-contact', 'data-label="{{ text_shopping_cart }}"']) assert.ok(header.includes(hook), hook);
  assert.ok(!/<span class="ocs-act-label">/.test(header), 'labels are <small>: core JS rewrites every span inside #wishlist-total');
  const sprite = read('custom/theme/ocs/template/ocs/sprite.twig');
  for (const id of ['search', 'cart', 'heart', 'compare', 'user', 'grid', 'chevron-right', 'chevron-down', 'truck', 'shield', 'return', 'headset', 'eye', 'phone', 'pin', 'mail', 'x', 'check', 'star']) assert.ok(sprite.includes('id="i-' + id + '"'), 'icon ' + id);
  const js = read('custom/theme/ocs/javascript/theme.js');
  assert.match(js, /data-badge/);
  assert.match(js, /getElementById\('ocs-compare-link'\)/);
  assert.ok(css.includes('.ocs-act-btn[data-count]::after'));
});

// TEST-VIS-005 - verifies REQ-VIS-003 (home: category menu, multi banners, accent heading, tabbed shelf)
test('visual identity: home composition pieces', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const slider = read('custom/extensions/ocs_home_slider/upload/catalog/view/theme/default/template/extension/module/ocs_home_slider.twig');
  for (const hook of ['ocs-home-menu', 'ocs-home-fly', 'has-menu', 'ocs-hero-multi']) assert.ok(slider.includes(hook), hook);
  const pc = read('custom/extensions/ocs_product_carousel/upload/catalog/view/theme/default/template/extension/module/ocs_product_carousel.twig');
  for (const hook of ['data-ocs-tab', 'data-ocs-panel', 'role="tablist"', 'ocs-sec-title']) assert.ok(pc.includes(hook), hook);
  const js = read('custom/theme/ocs/javascript/theme.js');
  assert.match(js, /data-ocs-tab/);
  assert.match(js, /ocsRefresh/);
  for (const rule of ['.ocs-home-menu li:hover > .ocs-home-fly', '.ocs-accent', '.ocs-chip.is-active', '@container home (min-width: 76rem)']) assert.ok(css.includes(rule), rule);
});

// TEST-VIS-006 - verifies REQ-VIS-005 (category: tree sidebar, sort chips in every listing template)
test('visual identity: category tree and sort chips', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  for (const t of ['category', 'search', 'special', 'manufacturer_info']) assert.ok(read('custom/theme/ocs/template/product/' + t + '.twig').includes('ocs-sortchips'), t);
  for (const rule of ['.list-group-item, a.list-group-item', '.ocs-sortchips', 'a.list-group-item.active']) assert.ok(css.includes(rule), rule);
});

// TEST-VIS-007 - verifies REQ-VIS-006 (product: sticky summary bar and zebra specification table)
test('visual identity: product sticky summary and specification table', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  assert.ok(read('custom/theme/ocs/template/product/product.twig').includes('id="ocs-sticky-buy"'));
  assert.match(read('custom/theme/ocs/javascript/theme.js'), /getElementById\('ocs-sticky-buy'\)/);
  assert.ok(css.includes('.ocs-sticky-buy.is-on') && css.includes('#tab-specification tbody tr:nth-child(odd) td'));
});

// TEST-VIS-008 - verifies REQ-VIS-009 (motion: keyframes, skeleton, empty state, reduced-motion switch)
test('visual identity: motion, skeleton and empty states respect reduced motion', () => {
  for (const rule of ['@keyframes ocs-fade', '@keyframes ocs-shimmer', '.ocs-skel', '.ocs-cart-empty::before']) assert.ok(css.includes(rule), rule);
  const reduce = css.slice(css.indexOf('@media (prefers-reduced-motion: reduce)\n{') >= 0 ? 0 : css.lastIndexOf('@media (prefers-reduced-motion: reduce)'));
  assert.ok(reduce.includes('.ocs-skel > span { animation: none; }'));
  assert.match(readFileSync(join(REPO_ROOT, 'custom/theme/ocs/javascript/theme.js'), 'utf8'), /ocs-skel/);
});

// TEST-VIS-009 - verifies REQ-VIS-010, REQ-VIS-021 (demo seed is local-only and idempotent; budgets)
test('visual identity: demo seed is local-only; theme budgets hold', () => {
  const seed = readFileSync(join(REPO_ROOT, 'scripts/seed-demo.php'), 'utf8');
  assert.match(seed, /APP_ENV=local/);
  assert.match(readFileSync(join(REPO_ROOT, 'scripts/lib/php/Setup/DemoSeed.php'), 'utf8'), /ON DUPLICATE KEY UPDATE/);
  assert.ok(Buffer.byteLength(css) <= 200 * 1024, 'css within 200 KB');
  assert.ok(Buffer.byteLength(readFileSync(join(REPO_ROOT, 'custom/theme/ocs/javascript/theme.js'), 'utf8')) <= 60 * 1024, 'theme.js within 60 KB');
});

// TEST-VIS-007 - verifies REQ-VIS-006, REQ-VIS-003 (vertical thumbnails by container width, review summary markup, brand "More" tile)
test('visual identity: vertical gallery thumbnails, review summary and the brand More tile', () => {
  const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
  const js = read('custom/theme/ocs/javascript/theme.js');
  assert.match(js, /direction: box\.getBoundingClientRect\(\)\.width >= 512 \? 'vertical' : 'horizontal'/);
  assert.ok(css.includes('.ocs-pdp > :first-child { container: gal / inline-size; }'), 'the container is the column, not the gallery itself');
  assert.ok(css.includes('@container gal (min-width: 32rem)') && css.includes('grid-template-areas: "thumbs main"') && css.includes('contain: size'));
  const twig = read('custom/theme/ocs/template/product/product.twig');
  for (const hook of ['ocs-stars-sum', 'ocs-stars-bars', '--pct: {{ r.pct }}%']) assert.ok(twig.includes(hook), hook);
  assert.ok(css.includes('.ocs-stars-bars i::after'));
  const brands = read('custom/extensions/ocs_brand_strip/upload/catalog/view/theme/default/template/extension/module/ocs_brand_strip.twig');
  assert.ok(brands.includes('ocs-brand-more') && brands.includes('{{ more }}'));
  assert.ok(css.includes('.ocs-sticky-buy:not(.is-on) { visibility: hidden;'), 'the hidden summary bar cannot show through the translucent header');
});
