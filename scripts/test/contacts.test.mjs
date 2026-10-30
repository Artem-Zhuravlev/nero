import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

// SPEC-047: pinned product card, footer, contacts settings.
const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');

// TEST-SIDE-001, TEST-SIDE-002 - verifies REQ-SIDE-001..004 (card beside the sections: sticky from 64rem, hidden below, no heading, one pinned summary)
test('side card: markup, sticky CSS and forwarding buttons', () => {
  const s = read('custom/theme/ocs/template/product/product.twig');
  const body = s.slice(s.indexOf('<div class="ocs-pdp-body">'));
  assert.ok(body.indexOf('class="ocs-pdp-sections"') < body.indexOf('<aside class="ocs-side-card"'), 'sections first, card beside');
  const aside = body.slice(body.indexOf('<aside'), body.indexOf('</aside>'));
  assert.ok(!/<h[1-6]/.test(aside), 'no heading in the card');
  for (const hook of ['class="ocs-side-title"', 'ocs-side-price', 'data-ocs-buy', "wishlist.add('{{ product_id }}')", "compare.add('{{ product_id }}')", 'ocs-card-tools']) assert.ok(aside.includes(hook), hook);
  assert.ok(!aside.includes('ocs_stickers'), 'no stickers in the pinned card');
  const css = read('custom/theme/ocs/stylesheet/stylesheet.css');
  const blk = css.slice(css.indexOf('/* ==== SPEC-047: pinned product card'));
  for (const rule of ['.ocs-side-card { display: none; }', '@media (min-width: 64rem)', 'position: sticky', 'var(--head-h, 0rem)', '.ocs-sticky-buy { display: none; }']) assert.ok(blk.includes(rule), rule);
  const js = read('custom/theme/ocs/javascript/theme.js');
  assert.ok(js.includes("document.querySelectorAll('[data-ocs-buy]')") && js.includes("b.hasAttribute('data-ocs-buy')"), 'buttons forward and show the in-cart state');
});

// TEST-FTR-001, TEST-FTR-003 - verifies REQ-FTR-001..004 (footer: contacts column, tel links, icon buttons, details groups, no apps)
test('footer markup: contacts, icons, details groups, no app links', () => {
  const f = read('custom/theme/ocs/template/common/footer.twig');
  for (const hook of ['class="ocs-foot-contacts"', '<address class="ocs-foot-row">', 'href="{{ phone.href }}"', 'ocs_shell.hours', 'ocs-soc-list', 'rel="noopener noreferrer"', 'ocs-crow-text', '<details class="ocs-fgroup" open>', '{{ powered }}', 'class="ocs-tabbar"']) assert.ok(f.includes(hook), hook);
  assert.ok(!/ocs_shell\.apps|ocs_apps/.test(f), 'no apps block');
  assert.ok(!/<ul|<li/.test(f.slice(f.indexOf('class="ocs-foot-contacts"'), f.indexOf('class="ocs-foot-groups"')).replace(/<ul class="(ocs-foot-phones|ocs-soc-list)[^>]*>[\s\S]*?<\/ul>/g, '')), 'lists only for phones and icons');
  const js = read('custom/theme/ocs/javascript/theme.js');
  assert.ok(js.includes("(min-width: 768px)") && js.includes('syncGroups'), 'groups follow the 768 px breakpoint');
  const css = read('custom/theme/ocs/stylesheet/stylesheet.css');
  for (const rule of ['@container foot (min-width: 62rem)', '.ocs-crow {', '.ocs-bchip {', '.ocs-fgroup > summary', 'max-width: 767.98px']) assert.ok(css.includes(rule), rule);
  for (const k of ['module_ocs_shell_apps']) {
    for (const p of ['custom/extensions/ocs_shell/extension.json', 'custom/extensions/ocs_shell/upload/admin/view/template/extension/module/ocs_shell.twig', 'custom/extensions/ocs_shell/upload/admin/controller/extension/module/ocs_shell.php']) assert.ok(!read(p).includes(k), `${k} in ${p}`);
  }
});

// TEST-SET-002 - verifies REQ-SET-001, REQ-SET-002 (theme form: language tabs, row editor, validation through Ocs\Contacts)
test('theme settings form has the contacts fieldset and sanitises on save', () => {
  const tw = read('custom/extensions/ocs/upload/admin/view/template/extension/theme/ocs.twig');
  for (const hook of ['theme_ocs_contact[{{ language.code }}][address]', 'theme_ocs_contact[{{ language.code }}][hours]', 'theme_ocs_contact[{{ language.code }}][phones]', 'theme_ocs_contact_links[', 'id="contact-link-add"', 'contact-link-template', 'data-ocs-remove', '[title][{{ language.code }}]']) assert.ok(tw.includes(hook), hook);
  const ctl = read('custom/extensions/ocs/upload/admin/controller/extension/theme/ocs.php');
  assert.ok(ctl.includes('Ocs\\Contacts::sanitize(') && ctl.includes("'theme_ocs_contact'"));
  const keys = (l) => [...read(`custom/extensions/ocs/upload/admin/language/${l}/extension/theme/ocs.php`).matchAll(/\$_\['([a-z_]+)'\]/g)].map((m) => m[1]).sort();
  assert.deepEqual(keys('en-gb'), keys('uk-ua'));
  for (const k of ['text_contacts', 'entry_address', 'entry_hours', 'entry_phones', 'text_contact_links']) assert.ok(keys('en-gb').includes(k), k);
});

// TEST-SET-003 - verifies REQ-SET-003, REQ-FTR-004 (migration copies once, apps setting deleted, shell reads theme settings first)
test('contacts migration and shell provider', () => {
  const mig = read('custom/db/migrations/0007_contacts_settings.php');
  for (const part of ["'theme_ocs_contact'", "'theme_ocs_contact_links'", "=== null", "delete('module_ocs_shell', 'module_ocs_shell_apps')"]) assert.ok(mig.includes(part), part);
  const ev = read('custom/extensions/ocs_shell/upload/catalog/controller/extension/ocs_shell/events.php');
  assert.ok(ev.includes("Ocs\\Contacts::view($this->config->get('theme_ocs_contact')") && ev.includes('module_ocs_shell_social'), 'theme settings first, old fields as fallback');
  assert.ok(existsSync(join(REPO_ROOT, 'custom/extensions/ocs/upload/system/library/ocs/contacts.php')));
});
