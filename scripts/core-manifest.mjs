// SPEC-001 REQ-ARCH-010: core/ must match core/.manifest.sha256
import { writeFileSync, readFileSync, existsSync } from 'node:fs';
import { resolve, join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { walk } from './lib/fs-walk.mjs';
import { sha256File } from './lib/hash.mjs';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const CORE = join(ROOT, 'core');
const MANIFEST = join(CORE, '.manifest.sha256');

export function computeManifest(coreDir = CORE) {
  return walk(coreDir)
    .filter((rel) => rel !== '.manifest.sha256')
    .map((rel) => `${sha256File(join(coreDir, rel))}  ${rel}`);
}

/** @returns {{ok: boolean, missing: string[], changed: string[], added: string[]}} */
export function checkManifest(coreDir = CORE, manifestPath = MANIFEST) {
  if (!existsSync(manifestPath)) return { ok: false, missing: [], changed: [], added: [], noManifest: true };
  const expected = new Map(
    readFileSync(manifestPath, 'utf8').split('\n').filter(Boolean).map((l) => {
      const i = l.indexOf('  ');
      return [l.slice(i + 2), l.slice(0, i)];
    }),
  );
  const actual = new Map(computeManifest(coreDir).map((l) => [l.slice(l.indexOf('  ') + 2), l.slice(0, l.indexOf('  '))]));
  const missing = [...expected.keys()].filter((k) => !actual.has(k));
  const added = [...actual.keys()].filter((k) => !expected.has(k));
  const changed = [...expected.keys()].filter((k) => actual.has(k) && actual.get(k) !== expected.get(k));
  return { ok: !missing.length && !added.length && !changed.length, missing, changed, added };
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  const cmd = process.argv[2];
  if (cmd === 'generate') {
    const lines = computeManifest();
    writeFileSync(MANIFEST, lines.join('\n') + '\n');
    console.log(`manifest written: ${lines.length} files`);
  } else if (cmd === 'check') {
    const r = checkManifest();
    if (r.noManifest) { console.error('FAIL core-manifest: core/.manifest.sha256 missing'); process.exit(5); }
    if (r.ok) console.log('PASS core-manifest');
    else {
      for (const k of ['missing', 'changed', 'added']) r[k].slice(0, 20).forEach((p) => console.error(`FAIL core-manifest ${k}: ${p}`));
      process.exit(5);
    }
  } else {
    console.error('usage: core-manifest.mjs generate|check');
    process.exit(1);
  }
}
