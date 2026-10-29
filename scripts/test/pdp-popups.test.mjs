import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

// SPEC-049: code pill, review / question popups with pros and cons, contact rows, inline language switch.
const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
const twig = () => read('custom/theme/ocs/template/product/product.twig');
const js = () => read('custom/theme/ocs/javascript/theme.js');
const css = () => read('custom/theme/ocs/stylesheet/stylesheet.css');

// TEST-COD-001 - verifies REQ-COD-001, REQ-COD-002 (code pill gated by the SKU switch, copy button with live region, fallback copy)
test('code pill: markup, SKU gate and copy handler', () => {
  const t = twig();
  assert.equal((t.match(/\{% if ocs_show_sku and model %\}/g) || []).length, 1, 'info column only (SPEC-098: not in the side card)');
  for (const hook of ['data-ocs-copy="{{ model }}"', 'data-done="{{ ocs_px.text_copied }}"', 'role="status" aria-live="polite"', 'type="button" class="ocs-copy"']) assert.ok(t.includes(hook), hook);
  const s = js();
  const copy = s.slice(s.indexOf('/* Copy buttons (SPEC-049'), s.indexOf('/* Product lightbox'));
  for (const hook of ['navigator.clipboard.writeText', "execCommand('copy')", 'is-done', "setAttribute('readonly'"]) assert.ok(copy.includes(hook), hook);
  assert.ok(read('custom/theme/ocs/template/ocs/sprite.twig').includes('id="i-copy"'));
});

// TEST-REV-001, TEST-REV-002 - verifies REQ-REV-001, REQ-REV-002, REQ-REV-004 (popup boxes, openers, pros / cons fields, messages inside the popup)
test('review and question forms live in popup boxes with pros and cons', () => {
  const t = twig();
  for (const id of ['ocs-review-box', 'ocs-question-box']) {
    assert.ok(t.includes(`id="${id}"`) && t.includes(`data-ocs-popup="${id}"`), id);
  }
  const form = t.slice(t.indexOf('id="form-review"'), t.indexOf('id="tab-questions"'));
  for (const hook of ['name="pros"', 'name="cons"', 'maxlength="255"', 'id="button-review"', 'id="review-msg"', 'data-ocs-popup-close', 'name="rating"']) assert.ok(form.includes(hook), hook);
  assert.ok(!/\$\('#review'\)\.after/.test(t), 'messages are written into the popup, not behind it');
  assert.ok(t.includes('OCS.popup'), 'closes after success');
  const s = js();
  const pop = s.slice(s.indexOf('/* Popups for the review and question forms'), s.indexOf('/* Copy buttons'));
  for (const hook of ['OCS.popup = {', 'onClose: restore', 'a[href="#form-review"]', 'while (box.firstChild)', 'while (active.holder.firstChild)']) assert.ok(pop.includes(hook), hook);
  assert.ok(s.startsWith("document.documentElement.className += ' ocs-js'"), 'JS marker for CSS');
  const c = css();
  assert.ok(c.includes('.ocs-js .ocs-popup-box { display: none; }') && c.includes('.ocs-js-only { display: none; }'), 'forms stay visible without JS');
});

// TEST-REV-003 - verifies REQ-REV-003, REQ-REV-005, REQ-REV-020 (table, events, guarded reads, list display, admin view + cleanup)
test('pros and cons: migration, events and guarded handlers', () => {
  const base = 'custom/extensions/ocs_product_extras';
  const mig = read(`${base}/db/003_review_extra.php`);
  for (const part of ['ocs_review_extra', 'catalog/model/catalog/review/addReview/after', 'catalog/view/product/review/before', 'admin/view/catalog/review_form/before', 'admin/model/catalog/review/deleteReview/before', 'ocs_review_extra.ocmod.xml']) assert.ok(mig.includes(part), part);
  const ext = JSON.parse(read(`${base}/extension.json`));
  assert.ok(ext.tables.includes('oc_ocs_review_extra') && ext.events.includes('ocs_product_extras_review_list'));
  const cat = read(`${base}/upload/catalog/controller/extension/ocs_product_extras/events.php`);
  for (const fn of ['afterAddReview', 'beforeReviewList']) {
    const body = cat.slice(cat.indexOf(`function ${fn}(`));
    assert.ok(/try \{[\s\S]*\} catch \([\\]Exception \$e\)/.test(body.slice(0, body.indexOf('\n\t}\n'))), `${fn} is guarded (a store without the table keeps its page)`);
  }
  assert.ok(read('custom/theme/ocs/template/product/review.twig').includes('review.pros') && read('custom/theme/ocs/template/product/review.twig').includes('review.cons'));
  assert.ok(read('custom/ocmod/ocs_review_extra.ocmod.xml').includes('{{ ocs_review_extra }}'));
  assert.ok(read(`${base}/upload/admin/controller/extension/ocs_product_extras/events.php`).includes('beforeDeleteReview'));
  // TEST-REV-030 - verifies REQ-REV-030..034 (reply column migration, editable form, save events, storefront reply)
  const m4 = read(`${base}/db/004_review_reply.php`);
  for (const part of ['addColumnIfMissing', 'reply', 'admin/model/catalog/review/addReview/after', 'admin/model/catalog/review/editReview/after']) assert.ok(m4.includes(part), part);
  const adm = read(`${base}/upload/admin/controller/extension/ocs_product_extras/events.php`);
  for (const part of ['name="ocs_reply"', "'ocs_pros'", "'ocs_cons'", 'afterAddReview', 'afterEditReview']) assert.ok(adm.includes(part), part);
  assert.ok(read('custom/theme/ocs/template/product/review.twig').includes('review.reply'));
  const keys = (side, l, f) => [...read(`${base}/upload/${side}/language/${l}/extension/${f}.php`).matchAll(/\$_\['([a-z_]+)'\]/g)].map((m) => m[1]).sort();
  for (const l of ['ru-ru', 'uk-ua']) assert.deepEqual(keys('catalog', 'en-gb', 'module/ocs_product_extras'), keys('catalog', l, 'module/ocs_product_extras'), l);
  assert.deepEqual(keys('admin', 'en-gb', 'ocs_product_extras/tab'), keys('admin', 'uk-ua', 'ocs_product_extras/tab'));
});

// TEST-CNT-001, TEST-CNT-002 - verifies REQ-CNT-001..003 (header and footer rows: coloured icon chip + text, brand tokens)
test('contact rows in the header dropdown and the footer', () => {
  const h = read('custom/theme/ocs/template/common/header.twig');
  const f = read('custom/theme/ocs/template/common/footer.twig');
  for (const [name, tpl] of [['header', h], ['footer', f]]) {
    assert.ok(tpl.includes('class="ocs-crow"') && tpl.includes('ocs-bchip ocs-bchip-{{ link.key }}') && tpl.includes('ocs-crow-text') && tpl.includes('rel="noopener noreferrer"'), name);
  }
  assert.ok(h.includes('ocs-bchip-phone') && h.includes('phone.label'), 'phones keep number and label');
  const c = css();
  for (const k of ['telegram', 'viber', 'messenger', 'whatsapp', 'phone', 'instagram', 'facebook', 'youtube']) {
    assert.ok(c.includes(`--brand-${k}:`) && c.includes(`.ocs-bchip-${k} {`), k);
  }
});

// TEST-LNG-001 - verifies REQ-LNG-001, REQ-LNG-002 (inline language links, core hooks kept, nothing for one language)
test('language switch is inline text with the core hooks', () => {
  const l = read('custom/theme/ocs/template/common/language.twig');
  for (const hook of ['{% if languages|length > 1 %}', 'id="form-language"', 'class="ocs-lang-btn language-select"', 'name="{{ language.code }}"', 'name="code"', 'name="redirect"', 'aria-current="true"', '|slice(0, 3)']) assert.ok(l.includes(hook), hook);
  assert.ok(!/dropdown|chevron|<img/.test(l), 'no dropdown, flag or chevron');
});
