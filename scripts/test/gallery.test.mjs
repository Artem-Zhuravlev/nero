import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

// SPEC-048: lightbox, YouTube video, info blocks.
const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
const js = () => read('custom/theme/ocs/javascript/theme.js');
const twig = () => read('custom/theme/ocs/template/product/product.twig');

// TEST-LBX-001, TEST-LBX-002 - verifies REQ-LBX-001..004 (lightbox on OCS.modal: stage, arrows, counter, thumbs, keyboard, swipe, summary; Magnific only without the gallery)
test('lightbox: module, markup hooks and styles', () => {
  const s = js();
  const lb = s.slice(s.indexOf('/* Product lightbox (SPEC-048'), s.indexOf('/* Option-level stock'));
  assert.ok(lb.length > 3000, 'lightbox block exists');
  for (const hook of ['OCS.lightbox = {open: open}', "'ocs-lb-stage'", 'ocs-lb-prev', 'ocs-lb-next', 'ocs-lb-count', 'ocs-lb-thumbs', "'ArrowLeft'", "'ArrowRight'", 'pointerdown', 'pointerup', 'data-ocs-buy-lb', "document.querySelector('.ocs-side-card [data-ocs-buy]')", "className: 'ocs-lb-dialog'", 'aria-live', 'aria-current', 'preload(index + 1)', 'e.stopPropagation()']) assert.ok(lb.includes(hook), hook);
  assert.ok(s.includes("(opts.className ? ' ' + opts.className : '')"), 'OCS.modal accepts a class');
  const t = twig();
  assert.ok(t.includes('data-ocs-lb="{{ ocs_px.lb }}"'));
  const mg = t.slice(t.indexOf('$(document).ready(function() {\n\t// the gallery opens'));
  assert.ok(mg.includes("if (!$('[data-ocs-gallery]').length)"), 'Magnific only for the fallback markup');
  const css = read('custom/theme/ocs/stylesheet/stylesheet.css');
  const blk = css.slice(css.indexOf('/* ==== SPEC-048'));
  for (const rule of ['.ocs-lb-stage', '.ocs-lb-thumb.is-current', '.ocs-lb-buy', '.ocs-lb-dialog', '@media (max-width: 767.98px)']) assert.ok(blk.includes(rule), rule);
});

// TEST-VID-002 - verifies REQ-VID-002, REQ-VID-020 (the video is the last slide and thumb; the iframe exists only after a click; nocookie, sandboxed)
test('video slide: poster button, iframe created on click only', () => {
  const t = twig();
  const main = t.slice(t.indexOf('class="ocs-gal-main'), t.indexOf('class="ocs-gal-thumbs'));
  assert.ok(main.lastIndexOf('ocs-gal-video') > main.lastIndexOf('{{ g.popup }}'), 'video slide after the image slides');
  assert.ok(!/<iframe/.test(t), 'no iframe in the page markup');
  assert.ok(t.includes('ocs-gal-vthumb') && t.includes('ocs_px.video.poster') && t.includes('data-ocs-video="{{ ocs_px.video.id }}"'));
  const s = js();
  const v = s.slice(s.indexOf('/* YouTube slides (SPEC-048'), s.indexOf('/* Product gallery on two linked Swipers'));
  for (const hook of ['youtube-nocookie.com/embed/', "setAttribute('sandbox'", "[A-Za-z0-9_-]{11}", 'allowfullscreen', 'OCS.video = {', 'stop: function']) assert.ok(v.includes(hook), hook);
  assert.ok(s.includes('window.OCS.video.stop(mainEl)'), 'leaving the slide stops the video');
});

// TEST-VID-001, TEST-VID-003 - verifies REQ-VID-001, REQ-VID-003 (admin field, save/delete, migration, event, badges on cards)
test('video: admin field, storage, list flag and badges', () => {
  const base = 'custom/extensions/ocs_product_extras';
  const mig = read(`${base}/db/002_video.php`);
  assert.ok(mig.includes('ocs_product_video') && mig.includes("'catalog/view/*/before'") && mig.includes('markVideo'));
  const ext = JSON.parse(read(`${base}/extension.json`));
  assert.ok(ext.tables.includes('oc_ocs_product_video') && ext.events.includes('ocs_product_extras_cards'));
  const adminEv = read(`${base}/upload/admin/controller/extension/ocs_product_extras/events.php`);
  assert.ok(adminEv.includes('Ocs\\Video::parse(') && adminEv.includes('setVideo(') && adminEv.includes("ocs_video_error"));
  assert.ok(read(`${base}/upload/admin/model/extension/ocs_product_extras/extras.php`).includes('ocs_product_video'), 'deleted with the product');
  assert.ok(read(`${base}/upload/admin/view/template/extension/ocs_product_extras/tab.twig`).includes('name="ocs_video"'));
  const keys = (side, l, f) => [...read(`${base}/upload/${side}/language/${l}/extension/${f}.php`).matchAll(/\$_\['([a-z_]+)'\]/g)].map((m) => m[1]).sort();
  assert.deepEqual(keys('admin', 'en-gb', 'ocs_product_extras/tab'), keys('admin', 'uk-ua', 'ocs_product_extras/tab'));
  for (const l of ['ru-ru', 'uk-ua']) assert.deepEqual(keys('catalog', 'en-gb', 'module/ocs_product_extras'), keys('catalog', l, 'module/ocs_product_extras'), l);
  for (const f of ['ocs/card.twig', 'extension/module/featured.twig', 'extension/module/latest.twig', 'extension/module/bestseller.twig', 'extension/module/special.twig', 'extension/module/featured_product.twig', 'product/product.twig']) {
    assert.ok(read(`custom/theme/ocs/template/${f}`).includes('product.has_video'), f);
  }
  assert.ok(read(`${base}/upload/catalog/model/extension/ocs_product_extras/extras.php`).includes('IN ('), 'one query for a whole list');
  assert.ok(existsSync(join(REPO_ROOT, `${base}/upload/system/library/ocs/video.php`)));
});

// TEST-INF-001, TEST-INF-002 - verifies REQ-INF-001, REQ-INF-002 (theme form editor, sanitising on save, rendering under the buy form without headings)
test('info blocks: theme form editor and product page markup', () => {
  const tw = read('custom/extensions/ocs/upload/admin/view/template/extension/theme/ocs.twig');
  for (const hook of ['theme_ocs_info_blocks[', 'id="info-block-add"', 'info-block-template', '[title][{{ language.code }}]', '[text][{{ language.code }}]']) assert.ok(tw.includes(hook), hook);
  const ctl = read('custom/extensions/ocs/upload/admin/controller/extension/theme/ocs.php');
  assert.ok(ctl.includes('Ocs\\InfoBlocks::sanitize('));
  assert.ok(read('custom/extensions/ocs/upload/catalog/controller/extension/ocs/events.php').includes("$data['ocs_info_blocks']"));
  const t = twig();
  const blocks = t.slice(t.indexOf('<div class="ocs-info-blocks">'), t.indexOf('<div class="ocs-info-blocks">') + 900);
  assert.ok(!/<h[1-6]|<ul|<li/.test(blocks), 'no headings or lists in the cards');
  const keys = (l) => [...read(`custom/extensions/ocs/upload/admin/language/${l}/extension/theme/ocs.php`).matchAll(/\$_\['([a-z_]+)'\]/g)].map((m) => m[1]).sort();
  assert.deepEqual(keys('en-gb'), keys('uk-ua'));
});

// TEST-VID-005 - verifies REQ-VID-001, REQ-SEC-020 (a store without the video table, i.e. before migration 002, still gets the whole product page data)
test('video lookups never take the product page data down', () => {
  const m = read('custom/extensions/ocs_product_extras/upload/catalog/model/extension/ocs_product_extras/extras.php');
  for (const fn of ['getVideoId', 'getVideoProductIds']) {
    const body = m.slice(m.indexOf(`function ${fn}(`));
    const end = body.indexOf('\n\t}\n');
    const code = body.slice(0, end);
    assert.ok(/try \{[\s\S]*ocs_product_video[\s\S]*\} catch \([\\]Exception \$e\)/.test(code), `${fn} catches a missing table`);
  }
});
