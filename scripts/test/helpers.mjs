// Test helpers: build a tiny throw-away repo layout (core + custom + env) in a temp dir.
import { mkdtempSync, mkdirSync, writeFileSync, copyFileSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

export const REPO_ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');

export function put(root, rel, content = 'x') {
  const p = join(root, rel);
  mkdirSync(dirname(p), { recursive: true });
  writeFileSync(p, content);
  return p;
}

export function makeRepo(extra = {}) {
  const root = mkdtempSync(join(tmpdir(), 'ocs-test-'));
  const up = 'core/upload/';
  put(root, `${up}index.php`, "<?php\ndefine('VERSION', '3.0.3.7');\ndefine('VERSION_CORE', 'ocStore');\ndefine('VERSION_BUILD', '0002');\n");
  put(root, `${up}.htaccess.txt`, 'Options -Indexes\nRewriteEngine On\nRewriteBase /\nRewriteRule ^system/storage/(.*) index.php?route=error/not_found [L]\n');
  put(root, `${up}config-dist.php`, '');
  put(root, `${up}admin/config-dist.php`, '');
  put(root, `${up}admin/index.php`, '<?php // admin');
  put(root, `${up}catalog/controller/common/home.php`, '<?php // home');
  put(root, `${up}install/index.php`, '<?php // installer');
  put(root, `${up}system/storage/index.html`, '');
  put(root, `${up}system/storage/vendor/autoload.php`, '<?php // autoload');
  put(root, `${up}image/catalog/cart.png`, 'png');
  put(root, `${up}robots.txt`, 'User-agent: *\n');
  for (const t of ['config.php.tpl', 'admin-config.php.tpl']) {
    put(root, `custom/config/${t}`, readFileSync(join(REPO_ROOT, 'custom', 'config', t), 'utf8'));
  }
  for (const [rel, content] of Object.entries(extra)) put(root, rel, content);
  return root;
}

export function baseEnv(root, over = {}) {
  return {
    APP_ENV: 'local',
    APP_URL: 'http://localhost:8080/',
    ADMIN_DIR: 'admin',
    DB_HOST: '127.0.0.1',
    DB_PORT: '3306',
    DB_NAME: 'ocstore',
    DB_USER: 'ocstore',
    DB_PASS: 'secret',
    DB_PREFIX: 'oc_',
    STORAGE_DIR: join(root, 'var', 'storage').replace(/\\/g, '/') + '/',
    ...over,
  };
}

export { copyFileSync };
