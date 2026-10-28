// SPEC-104: brands as a separate main-menu item.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { REPO_ROOT } from './helpers.mjs';

const read = (p) => readFileSync(join(REPO_ROOT, p), 'utf8');
const ext = 'custom/extensions/ocs_mega_menu/';
const twig = read(ext + 'upload/catalog/view/theme/default/template/extension/module/ocs_mega_menu.twig');
const ctl = read(ext + 'upload/catalog/controller/extension/module/ocs_mega_menu.php');
const adminCtl = read(ext + 'upload/admin/controller/extension/module/ocs_mega_menu.php');
const adminTwig = read(ext + 'upload/admin/view/template/extension/module/ocs_mega_menu.twig');
const model = read('custom/extensions/ocs_menu/upload/catalog/model/extension/ocs_menu/tree.php');
const css = read('custom/theme/ocs/stylesheet/stylesheet.css');

// TEST-MMB-001 - verifies REQ-MMB-001, REQ-MMB-002, REQ-MMB-003, REQ-MMB-004, REQ-MMB-005
test('brands are one last menu item with all store brands, switchable in admin', () => {
  assert.doesNotMatch(twig, /ocs-mega-brands\b/);
  assert.doesNotMatch(css, /\.ocs-mega-brands\s*\{/);
  assert.doesNotMatch(ctl + adminCtl + adminTwig + model, /brands_limit|getBrands\(/);
  assert.match(twig, /<\/ul>/);
  assert.ok(twig.indexOf('ocs-mega-branditem') > twig.indexOf('{% endfor %}'));
  assert.match(twig, /ocs-mega-branditem ocs-has-children/);
  assert.match(ctl, /module_ocs_mega_menu_brands'\)[\s\S]*getAllBrands\(\)/);
  assert.match(adminTwig, /module_ocs_mega_menu_brands/);
  assert.match(model, /manufacturer_to_store[\s\S]*ORDER BY m\.sort_order/);
  assert.match(model, /cache->set\(\$key/);
  assert.match(css, /\.ocs-mega-brandlist\s*\{/);
});
