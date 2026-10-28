import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

// SPEC-050: cart badge, top menu, cart page, subcategory carousel.
const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
const css = () => read('custom/theme/ocs/stylesheet/stylesheet.css');

// TEST-BDG-001 - verifies REQ-BDG-001 (attr() in a pseudo-element reads its own element: every attribute used that way must be written on that element)
test('badge: the script writes data-badge / data-count on the button that owns the ::after', () => {
  const c = css();
  assert.match(c, /\.ocs-head-cart\[data-count\] #cart > \.btn::after \{ content: attr\(data-badge\); \}/, 'the cart badge reads data-badge of the button');
  const js = read('custom/theme/ocs/javascript/theme.js');
  assert.ok(js.includes("btn.setAttribute('data-badge', badge)") && js.includes("btn.setAttribute('data-count', n)"), 'written on the button');
});

// TEST-TOP-002, TEST-TOP-003 - verifies REQ-TOP-001..003 (settings editor, header markup, sale token, phone sheet)
test('top menu: settings form, header markup and styles', () => {
  const tw = read('custom/extensions/ocs/upload/admin/view/template/extension/theme/ocs.twig');
  for (const hook of ['theme_ocs_top_menu[enabled]', 'theme_ocs_top_menu[title][{{ language.code }}]', 'theme_ocs_top_menu[sale]', 'theme_ocs_top_menu[blog]', 'theme_ocs_top_menu[contacts]', 'theme_ocs_top_menu[pages][{{ page.id }}][on]', '[sort]']) assert.ok(tw.includes(hook), hook);
  assert.ok(read('custom/extensions/ocs/upload/admin/controller/extension/theme/ocs.php').includes('Ocs\\TopMenu::sanitize('));
  const h = read('custom/theme/ocs/template/common/header.twig');
  for (const hook of ['class="ocs-top"', 'ocs-top-sale', '#i-percent', 'dropdown-menu-right', 'aria-current="page"', 'not (ocs_top and ocs_top.blog)']) assert.ok(h.includes(hook), hook);
  assert.ok(h.indexOf('ocs_top.contacts') < h.indexOf('ocs-top-sale') && h.indexOf('ocs-top-sale') < h.indexOf('ocs_top.blog %}<a') && h.indexOf('ocs_top.blog %}<a') < h.indexOf('ocs-top-drop'), 'TEST-TOP-031 contacts, sale, blog, then the dropdown');
  const c = css();
  assert.ok(c.includes('--sale: #d70015;') && c.includes('.ocs-top-sale') && c.includes('position: fixed; inset-inline'), 'sale token and the phone sheet');
  const keys = (l, f, side) => [...read(`custom/extensions/ocs/upload/${side}/language/${l}/extension/${f}.php`).matchAll(/\$_\['([a-z_]+)'\]/g)].map((m) => m[1]).sort();
  assert.deepEqual(keys('en-gb', 'ocs/top', 'catalog'), keys('uk-ua', 'ocs/top', 'catalog'));
  assert.deepEqual(keys('en-gb', 'ocs/top', 'catalog'), keys('ru-ru', 'ocs/top', 'catalog'));
  assert.deepEqual(keys('en-gb', 'theme/ocs', 'admin'), keys('uk-ua', 'theme/ocs', 'admin'));
  const ev = read('custom/extensions/ocs/upload/catalog/controller/extension/ocs/events.php');
  assert.ok(/private function topMenu\(\)[\s\S]*try \{[\s\S]*catch \([\\]Exception \$e\)/.test(ev), 'guarded');
});

// TEST-CRT-001, TEST-CRT-002 - verifies REQ-CRT-001..003 (cart page: core hooks kept, old prices, summary, mobile cards)
test('cart page: hooks, struck old prices, summary box and card layout', () => {
  const t = read('custom/theme/ocs/template/checkout/cart.twig');
  for (const hook of ['name="quantity[{{ product.cart_id }}]"', "cart.remove('{{ product.cart_id }}')", "voucher.remove('{{ voucher.key }}')", 'id="accordion"', '{{ module }}', 'product.old_price', 'product.old_total', '<s class="ocs-old">', 'ocs-cart-sum', 'class="ocs-sum"', '{{ checkout }}', '{{ continue }}', 'data-label="{{ column_price }}"']) assert.ok(t.includes(hook), hook);
  assert.ok(!/fa fa-|style="max-width/.test(t) && !/<h2>\s*\{\{ (price|total)/.test(t));
  const ev = read('custom/extensions/ocs/upload/catalog/controller/extension/ocs/events.php');
  const old = ev.slice(ev.indexOf('private function cartOldPrices'), ev.indexOf('private function topMenu'));
  assert.ok(old.includes('$old > $current + 0.004') && old.includes('catch (\\Exception $e)') && !/session->data\['cart|cart->add|cart->update/.test(old), 'display only, never below the current price, guarded');
  const c = css();
  for (const rule of ['.ocs-cart-table', '.ocs-cart-sum', '@media (max-width: 767.98px)', 'grid-template-areas: "img name name"']) assert.ok(c.includes(rule), rule);
  assert.ok(!/!important/.test(c.slice(c.indexOf('/* ==== SPEC-050'))), 'no new !important');
});

// TEST-SUB-001 - verifies REQ-SUB-001, REQ-SUB-002 (subcategories: carousel of image cards, guarded image lookup, no chips or refine lists)
test('subcategories: carousel markup, guarded thumbs', () => {
  const t = read('custom/theme/ocs/template/product/category.twig');
  for (const hook of ['class="ocs-subcats"', 'ocs-subcat-carousel', 'ocs-carousel-track', 'class="ocs-subcat"', 'category.thumb', 'ocs-carousel-prev', 'ocs-carousel-next']) assert.ok(t.includes(hook), hook);
  assert.ok(!t.includes('class="ocs-chips"') && !t.includes('text_refine }}</h2>'), 'chips and refine lists are gone');
  const ev = read('custom/extensions/ocs_listing/upload/catalog/controller/extension/ocs_listing/events.php');
  const fn = ev.slice(ev.indexOf('private function subcategoryImages'), ev.indexOf('private function sorts'));
  assert.ok(/try \{[\s\S]*\} catch \([\\]Exception \$e\)/.test(fn) && fn.includes('getCategories('), 'guarded, same call as the core');
  assert.ok(css().includes('.ocs-subcat-img'));
});
