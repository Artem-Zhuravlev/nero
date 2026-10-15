// SPEC-003: compose core/ + custom/ into build/www with generated configuration.
import {
  readFileSync, writeFileSync, mkdirSync, existsSync, copyFileSync, renameSync, rmSync, accessSync, constants, readdirSync, rmdirSync, statSync,
} from 'node:fs';
import { resolve, join, dirname, relative, isAbsolute } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';
import { walk } from './lib/fs-walk.mjs';
import { sha256, sha256File } from './lib/hash.mjs';
import { loadEnvFile, validateEnv, EnvError } from './lib/env.mjs';
import { render, TemplateError } from './lib/render.mjs';
import { collectLayers, RUNTIME_DIRS } from './lib/layers.mjs';
import { checkManifest } from './core-manifest.mjs';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const posix = (p) => p.replace(/\\/g, '/');
const withSlash = (p) => (p.endsWith('/') ? p : `${p}/`);

function writeAtomic(path, content) {
  mkdirSync(dirname(path), { recursive: true });
  const tmp = `${path}.tmp-${process.pid}`;
  writeFileSync(tmp, content);
  renameSync(tmp, path);
}

function gitInfo(root) {
  const run = (args) => execFileSync('git', args, { cwd: root, stdio: ['ignore', 'pipe', 'ignore'] }).toString().trim();
  try {
    return { commit: run(['rev-parse', '--short=12', 'HEAD']), dirty: run(['status', '--porcelain']) !== '' };
  } catch {
    return { commit: null, dirty: false };
  }
}

function buildHtaccess(coreDir, appUrl) {
  const stock = readFileSync(join(coreDir, 'upload', '.htaccess.txt'), 'utf8');
  const basePath = new URL(appUrl).pathname;
  const deny = [
    '# Added by scripts/build.mjs (SPEC-003 REQ-BLD-032)',
    'RewriteRule ^(custom|specs|docs|scripts|var|build|core)/ - [F,L]',
    '<FilesMatch "(?i)\\.(sql|env|git|md|json|lock|sh|bak)$">',
    ' Require all denied',
    '</FilesMatch>',
    '',
  ].join('\n');
  if (!/^RewriteBase \/.*$/m.test(stock)) throw new TemplateError('.htaccess.txt: RewriteBase line not found');
  return stock.replace(/^RewriteBase \/.*$/m, () => `RewriteBase ${basePath}\n${deny}`);
}

const STORAGE_SUBDIRS = ['cache', 'logs', 'modification', 'session', 'upload', 'download'];

function syncStorage({ coreDir, storageDir }) {
  const src = join(coreDir, 'upload', 'system', 'storage');
  for (const sub of STORAGE_SUBDIRS) mkdirSync(join(storageDir, sub), { recursive: true });
  let added = 0;
  let updated = 0;
  for (const rel of walk(src)) {
    const dst = join(storageDir, rel);
    if (!existsSync(dst)) {
      mkdirSync(dirname(dst), { recursive: true });
      copyFileSync(join(src, rel), dst);
      added++;
    } else if (rel.startsWith('vendor/') && sha256File(join(src, rel)) !== sha256File(dst)) {
      copyFileSync(join(src, rel), dst);
      updated++;
    }
  }
  for (const sub of STORAGE_SUBDIRS) {
    try {
      accessSync(join(storageDir, sub), constants.W_OK);
    } catch {
      return { error: `not writable: ${join(storageDir, sub)}` };
    }
  }
  return { added, updated };
}

function pruneEmptyDirs(dir, out) {
  for (const name of readdirSync(dir)) {
    const p = join(dir, name);
    if (!statSync(p).isDirectory()) continue;
    if (RUNTIME_DIRS.some((d) => `${posix(relative(out, p))}/` === d)) continue;
    pruneEmptyDirs(p, out);
    if (!readdirSync(p).length) rmdirSync(p);
  }
}

/**
 * @param {object} o  options (all optional): root, coreDir, customDir, out, env, envFile, check, skipManifest, quiet
 * @returns {{exit:number, stats?:object, messages:string[]}}
 */
export function build(o = {}) {
  const root = o.root ?? ROOT;
  const coreDir = o.coreDir ?? join(root, 'core');
  const customDir = o.customDir ?? join(root, 'custom');
  const out = resolve(o.out ?? join(root, 'build', 'www'));
  const messages = [];
  const log = (m) => { messages.push(m); if (!o.quiet) console.log(m); };
  const fail = (exit, m) => { messages.push(m); if (!o.quiet) console.error(m); return { exit, messages }; };

  if (!o.skipManifest) {
    const r = checkManifest(coreDir, join(coreDir, '.manifest.sha256'));
    if (!r.ok) {
      return fail(5, `FAIL core-manifest: core/ does not match core/.manifest.sha256 (changed=${r.changed?.length ?? 0} missing=${r.missing?.length ?? 0} added=${r.added?.length ?? 0})`);
    }
  }

  let env;
  try {
    env = validateEnv(o.env ?? loadEnvFile(o.envFile ?? join(root, '.env')));
  } catch (e) {
    if (e instanceof EnvError) return fail(3, `FAIL env: ${e.message}`);
    throw e;
  }

  const storageDir = withSlash(posix(resolve(root, env.STORAGE_DIR)));
  const insideOut = !posix(relative(out, storageDir)).startsWith('..');
  if (insideOut) return fail(4, 'FAIL storage: STORAGE_DIR must not be inside the docroot');
  for (const forbidden of ['core', 'custom', 'scripts', 'specs', 'docs']) {
    const r = posix(relative(join(root, forbidden), storageDir));
    if (!r.startsWith('..') && !isAbsolute(r)) return fail(4, `FAIL storage: STORAGE_DIR must not be inside ${forbidden}/`);
  }

  let layers;
  try {
    layers = collectLayers({ coreDir, customDir, adminDir: env.ADMIN_DIR });
  } catch (e) {
    return fail(1, `FAIL collect: ${e.message}`);
  }
  if (layers.collisions.length) {
    layers.collisions.forEach((c) => log(c));
    return fail(2, `FAIL collisions: ${layers.collisions.length}`);
  }

  const values = { ...env, DOCROOT: withSlash(posix(out)), STORAGE_DIR: storageDir };
  let generated;
  try {
    generated = new Map([
      ['config.php', render(readFileSync(join(customDir, 'config', 'config.php.tpl'), 'utf8'), values, 'config.php.tpl')],
      [`${env.ADMIN_DIR}/config.php`, render(readFileSync(join(customDir, 'config', 'admin-config.php.tpl'), 'utf8'), values, 'admin-config.php.tpl')],
      ['.htaccess', buildHtaccess(coreDir, env.APP_URL)],
    ]);
  } catch (e) {
    if (e instanceof TemplateError || e.code === 'ENOENT') return fail(3, `FAIL template: ${e.message}`);
    throw e;
  }

  const expected = new Map();
  for (const f of layers.files) expected.set(f.rel, { abs: f.abs });
  for (const [r, content] of generated) expected.set(r, { content });

  const lines = [...expected.keys()].sort().map((r) => {
    const e = expected.get(r);
    return `${e.content !== undefined ? sha256(e.content) : sha256File(e.abs)}  ${r}`;
  });
  // The docroot path is part of the generated configs, so it is part of the hash by design.
  const treeHash = sha256(lines.join('\n'));
  const stats = { files: expected.size, added: 0, updated: 0, unchanged: 0, removed: 0, treeHash };

  if (o.check) {
    log(`check ok: files=${stats.files} treeHash=${treeHash.slice(0, 12)}`);
    return { exit: 0, stats, messages };
  }

  for (const [r, e] of expected) {
    const dst = join(out, r);
    const existed = existsSync(dst);
    if (e.content !== undefined) {
      if (existed && readFileSync(dst, 'utf8') === e.content) stats.unchanged++;
      else { writeAtomic(dst, e.content); stats[existed ? 'updated' : 'added']++; }
    } else if (!existed) {
      mkdirSync(dirname(dst), { recursive: true });
      copyFileSync(e.abs, dst);
      stats.added++;
    } else if (sha256File(e.abs) !== sha256File(dst)) {
      copyFileSync(e.abs, dst);
      stats.updated++;
    } else {
      stats.unchanged++;
    }
  }

  if (existsSync(out)) {
    for (const r of walk(out)) {
      if (expected.has(r) || RUNTIME_DIRS.some((d) => r.startsWith(d))) continue;
      rmSync(join(out, r));
      stats.removed++;
    }
    pruneEmptyDirs(out, out);
  }

  const st = syncStorage({ coreDir, storageDir });
  if (st.error) return fail(4, `FAIL storage: ${st.error}`);

  const infoPath = join(dirname(out), 'BUILD_INFO.json');
  let keep = false;
  try { keep = JSON.parse(readFileSync(infoPath, 'utf8')).treeHash === treeHash; } catch { /* no previous info */ }
  if (!keep) {
    const g = gitInfo(root);
    const idx = readFileSync(join(coreDir, 'upload', 'index.php'), 'utf8');
    const ver = /define\('VERSION', '([^']+)'\)/.exec(idx);
    const bld = /define\('VERSION_BUILD', '([^']+)'\)/.exec(idx);
    const extRoot = join(customDir, 'extensions');
    const extensions = existsSync(extRoot)
      ? readdirSync(extRoot).sort()
        .filter((n) => existsSync(join(extRoot, n, 'extension.json')))
        .map((n) => {
          const j = JSON.parse(readFileSync(join(extRoot, n, 'extension.json'), 'utf8'));
          return { code: j.code, version: j.version };
        })
      : [];
    writeAtomic(infoPath, `${JSON.stringify({
      core: ver ? `ocStore ${ver[1]} ${bld ? bld[1] : ''}`.trim() : 'unknown',
      gitCommit: g.commit,
      gitDirty: g.dirty,
      builtAt: new Date().toISOString(),
      appEnv: env.APP_ENV,
      adminDir: env.ADMIN_DIR,
      extensions,
      theme: 'ocs',
      files: stats.files,
      treeHash,
    }, null, 2)}\n`);
  }

  log(`build ok: files=${stats.files} added=${stats.added} updated=${stats.updated} unchanged=${stats.unchanged} removed=${stats.removed} treeHash=${treeHash.slice(0, 12)}`);
  return { exit: 0, stats, messages };
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  const a = process.argv.slice(2);
  const opt = (name) => { const i = a.indexOf(name); return i >= 0 ? a[i + 1] : undefined; };
  const known = ['--env-file', '--out', '--check', '--verbose', '--no-manifest'];
  const bad = a.filter((x) => x.startsWith('--') && !known.includes(x));
  if (bad.length) {
    console.error('usage: build.mjs [--env-file path] [--out dir] [--check] [--no-manifest]');
    process.exit(1);
  }
  const r = build({ envFile: opt('--env-file'), out: opt('--out'), check: a.includes('--check'), skipManifest: a.includes('--no-manifest') });
  process.exit(r.exit);
}
