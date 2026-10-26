import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

// SPEC-046: form system and stacked product sections.
const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
const twig = () => read('custom/theme/ocs/template/product/product.twig');
const css = () => read('custom/theme/ocs/stylesheet/stylesheet.css');

// TEST-SEC-001 - verifies REQ-SEC-001, REQ-SEC-002 (stacked sections with h2, ids kept, no tabs)
test('product page sections replace the tabs and keep their ids', () => {
  const s = twig();
  assert.ok(!/nav-tabs|tab-content|tab-pane|data-toggle="tab"/.test(s), 'no Bootstrap tabs');
  let last = -1;
  for (const id of ['tab-description', 'tab-specification', 'tab-review', 'tab-questions']) {
    const at = s.indexOf(`<section class="ocs-section" id="${id}">`);
    assert.ok(at > last, `${id} in order`);
    last = at;
    assert.match(s.slice(at, at + 120), /<h2>/, `${id} has an h2`);
  }
  for (const id of ['review', 'form-review', 'button-review']) assert.ok(s.includes(`id="${id}"`), id);
  assert.ok(s.includes('{% if attribute_groups %}') && s.includes('{% if review_status %}') && s.includes('{% if ocs_px.qa %}'), 'empty sections are not rendered');
  assert.ok(!s.includes("trigger('click')") || !/#tab-review\']/.test(s), 'rating links are anchors');
  assert.ok(s.includes('<a href="#tab-review">') && s.includes('<a href="#form-review">'));
});

// TEST-SEC-003 - verifies REQ-SEC-003 (section bar: anchors with scroll-spy, no tab hooks)
test('section bar is an anchor menu with scroll-spy', () => {
  const js = read('custom/theme/ocs/javascript/theme.js');
  const bar = js.slice(js.indexOf('/* Product section bar'), js.indexOf('/* City selector'));
  assert.ok(bar.includes('IntersectionObserver') && bar.includes('scrollTo'));
  assert.ok(!/shown\.bs\.tab|\.tab\('show'\)|data-ocs-tab/.test(bar), 'no tab hooks');
});

// TEST-FRM-001 - verifies REQ-FRM-001, REQ-FRM-002 (one form system, tokens only)
test('form system covers fields, states and controls with tokens', () => {
  const c = css();
  const sys = c.slice(c.indexOf('/* ==== SPEC-046'));
  for (const rule of ['.form-control:focus', '.form-control[disabled]', '.has-error .form-control', 'select.form-control', 'input[type="checkbox"]:checked', 'input[type="radio"]:checked', '::file-selector-button', '.ocs-form-grid', '.ocs-form-actions', '.text-danger', '.ocs-help']) assert.ok(sys.includes(rule), rule);
  assert.equal((c.match(/\.form-control, select\.form-control, textarea\.form-control \{/g) || []).length, 1, 'one definition of the field block');
  assert.ok(!/(padding|margin|gap)[a-z-]*: [^;]*\b\d+px/.test(sys), 'no raw px spacing in the new block');
});

// TEST-FRM-003 - verifies REQ-FRM-003 (star picker over the rating radios, fieldset + legend, keyboard via native radios)
test('review form: star picker on radios, labelled fields', () => {
  const s = twig();
  const form = s.slice(s.indexOf('id="form-review"'), s.indexOf('id="tab-questions"'));
  assert.match(form, /<fieldset class="ocs-field required ocs-rating-field">\s*<legend>/);
  assert.equal((form.match(/type="radio" name="rating"/g) || []).length, 1, 'one radio in the loop');
  assert.ok(form.includes('{% for i in 5..1 %}') && form.includes('name="rating" value="{{ i }}"') && form.includes('for="input-rating-{{ i }}"'));
  for (const f of ['input-name', 'input-review']) assert.ok(form.includes(`for="${f}"`) && form.includes(`id="${f}"`), f);
  const c = css();
  assert.ok(c.includes('.ocs-rate input:checked ~ label') && c.includes('.ocs-rate label:hover ~ label') && c.includes('.ocs-rate input:focus-visible + label'));
});

// TEST-FRM-004 - verifies REQ-FRM-004 (question form: labelled grid, status block, JS hooks kept)
test('question form is labelled and keeps the newsletter-form hooks', () => {
  const s = twig();
  const form = s.slice(s.indexOf('class="ocs-form ocs-news-form ocs-q-form"'), s.indexOf('</form>', s.indexOf('ocs-q-form')));
  for (const hook of ['name="token"', 'name="website"', 'for="input-q-name"', 'for="input-q-email"', 'for="input-q-text"', 'class="ocs-news-msg" role="status" aria-live="polite"', 'class="ocs-form-grid"']) assert.ok(form.includes(hook), hook);
});
