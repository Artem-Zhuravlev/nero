// SPEC-040: npm run pack:theme [-- --out <dir>]  ->  dist/ocs-theme-<version>.ocmod.zip
import { resolve, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { existsSync } from 'node:fs';
import { packTheme, PackError } from './lib/pack-theme.mjs';
import { loadEnvFile } from './lib/env.mjs';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const a = process.argv.slice(2);
const outIdx = a.indexOf('--out');
const outDir = resolve(ROOT, outIdx >= 0 ? a[outIdx + 1] : 'dist');

let envValues = [];
if (existsSync(join(ROOT, '.env'))) {
  try {
    envValues = Object.entries(loadEnvFile(join(ROOT, '.env')))
      .filter(([k, v]) => /PASS|SECRET|KEY|TOKEN/.test(k) && String(v).length >= 6)
      .map(([, v]) => String(v));
  } catch {
    envValues = [];
  }
}

try {
  const r = packTheme({ root: ROOT, outDir, envValues });
  console.log(`packed ${r.file}\n  version ${r.version}, ${r.files} files, ${(r.bytes / 1024).toFixed(0)} KiB`);
} catch (e) {
  if (e instanceof PackError) {
    console.error(`FAIL pack:theme ${e.message}`);
    process.exit(1);
  }
  throw e;
}
