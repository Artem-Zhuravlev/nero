// Check implementations for scripts/verify.mjs (SPEC-009). Each check: {id, severity, run(ctx) -> Finding[]}.
// Finding: {severity, check, path, line, message, req}
import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import { execFileSync } from 'node:child_process';
import { walk } from './fs-walk.mjs';
import { checkManifest } from '../core-manifest.mjs';
import { build } from '../build.mjs';
import { parseEnv } from './env.mjs';

const finding = (check, severity, path, message, req = '', line = 0) => ({ severity, check, path, line, message, req });

export const REQUIRED_PATHS = [
  'CLAUDE.md', 'README.md', 'package.json', '.env.example', '.gitignore',
  'core/upload/index.php', 'core/.manifest.sha256',
  'custom/overlay', 'custom/theme/ocs', 'custom/extensions', 'custom/ocmod', 'custom/db/migrations', 'custom/db/seed', 'custom/config/config.php.tpl', 'custom/config/admin-config.php.tpl',
  'scripts', 'specs/README.md', 'docs/ocstore-internals.md', 'docs/agent-playbook.md',
];

const coreManifest = {
  id: 'core-manifest',
  severity: 'error',
  run: ({ root }) => {
    const core = join(root, 'core');
    const r = checkManifest(core, join(core, '.manifest.sha256'));
    if (r.ok) return [];
    if (r.noManifest) return [finding('core-manifest', 'error', 'core/.manifest.sha256', 'manifest missing', 'REQ-ARCH-010')];
    return ['missing', 'changed', 'added'].flatMap((k) => r[k].slice(0, 20).map((p) => finding('core-manifest', 'error', `core/${p}`, `core file ${k}`, 'REQ-ARCH-010')));
  },
};

const layout = {
  id: 'layout',
  severity: 'error',
  run: ({ root }) => {
    const out = REQUIRED_PATHS.filter((p) => !existsSync(join(root, p))).map((p) => finding('layout', 'error', p, 'required path missing', 'REQ-ARCH-011'));
    const extRoot = join(root, 'custom', 'extensions');
    if (existsSync(extRoot)) {
      for (const name of readdirSync(extRoot)) {
        if (name === '.gitkeep') continue;
        if (!existsSync(join(extRoot, name, 'extension.json'))) out.push(finding('layout', 'error', `custom/extensions/${name}`, 'extension.json missing', 'REQ-EXT-002'));
      }
    }
    return out;
  },
};

const phpLint = {
  id: 'php-lint',
  severity: 'error',
  run: ({ root, phpBin }) => {
    const files = ['custom', 'scripts'].flatMap((d) => walk(join(root, d)).filter((f) => f.endsWith('.php')).map((f) => `${d}/${f}`));
    const out = [];
    for (const f of files) {
      try {
        execFileSync(phpBin, ['-l', join(root, f)], { stdio: ['ignore', 'pipe', 'pipe'] });
      } catch (e) {
        out.push(finding('php-lint', 'error', f, String(e.stdout || e.stderr || e.message).trim().split('\n')[0], 'REQ-ARCH-080'));
      }
    }
    return out;
  },
};

const buildCheck = {
  id: 'build-check',
  severity: 'error',
  run: ({ root }) => {
    const envPath = existsSync(join(root, '.env')) ? join(root, '.env') : join(root, '.env.example');
    const env = parseEnv(readFileSync(envPath, 'utf8'));
    const r = build({ root, env, check: true, skipManifest: true, quiet: true });
    if (r.exit === 0) return [];
    return r.messages.filter((m) => /^(COLLISION|INVALID|FAIL)/.test(m)).map((m) => finding('build-check', 'error', 'custom/', m, 'REQ-BLD-010'));
  },
};

/** REQ <-> tasks traceability (SPEC-009 REQ-QA-040, 042, 044). */
const traceability = {
  id: 'traceability',
  severity: 'error',
  run: ({ root }) => {
    const specsDir = join(root, 'specs');
    const dirs = readdirSync(specsDir).filter((d) => d !== '_template' && existsSync(join(specsDir, d, 'spec.md')));
    const out = [];
    const defined = new Set();
    const texts = [];
    const readme = readFileSync(join(specsDir, 'README.md'), 'utf8');
    for (const d of dirs) {
      const spec = readFileSync(join(specsDir, d, 'spec.md'), 'utf8');
      texts.push(spec);
      const status = /^\*\*Status:\*\*\s*(\S+)/m.exec(spec)?.[1];
      const id = /^# (SPEC-\d+)/m.exec(spec)?.[1];
      const row = id && new RegExp(`\\| ${id} \\|.*\`${status}\``).test(readme);
      if (!id || !row) out.push(finding('traceability', 'error', `specs/${d}/spec.md`, `specs/README.md row for ${id ?? d} missing or status != ${status}`, 'REQ-QA-044'));
      const defs = [...spec.matchAll(/\*\*(REQ-[A-Z0-9]+-\d+)\*\*/g)].map((m) => m[1]);
      defs.forEach((x) => defined.add(x));
      const tasksPath = join(specsDir, d, 'tasks.md');
      if (!existsSync(tasksPath)) { out.push(finding('traceability', 'error', `specs/${d}`, 'tasks.md missing', 'REQ-QA-040')); continue; }
      const t = readFileSync(tasksPath, 'utf8');
      const cov = new Set();
      for (const m of t.matchAll(/REQ-([A-Z0-9]+)-(\d+)((?:\s*(?:,|\.\.)\s*\d+)*)/g)) {
        cov.add(`REQ-${m[1]}-${m[2]}`);
        let prev = parseInt(m[2], 10);
        for (const k of m[3].matchAll(/(,|\.\.)\s*(\d+)/g)) {
          const n = parseInt(k[2], 10);
          if (k[1] === '..') for (let i = prev; i <= n; i++) cov.add(`REQ-${m[1]}-${String(i).padStart(3, '0')}`);
          else cov.add(`REQ-${m[1]}-${k[2]}`);
          prev = n;
        }
      }
      for (const x of defs.filter((r) => !cov.has(r))) out.push(finding('traceability', 'error', `specs/${d}/tasks.md`, `${x} has no task`, 'REQ-QA-040'));
    }
    const all = texts.join('\n');
    const dup = [...new Set([...all.matchAll(/\*\*(REQ-[A-Z0-9]+-\d+)\*\*/g)].map((m) => m[1]))];
    void dup;
    for (const d of dirs) {
      const spec = readFileSync(join(specsDir, d, 'spec.md'), 'utf8');
      const defs = [...spec.matchAll(/\*\*(REQ-[A-Z0-9]+-\d+)\*\*/g)].map((m) => m[1]);
      defs.filter((x, i) => defs.indexOf(x) !== i).forEach((x) => out.push(finding('traceability', 'error', `specs/${d}/spec.md`, `${x} defined twice`, 'REQ-QA-040')));
    }
    for (const f of ['docs/ocstore-reference.md', 'docs/ocstore-internals.md', 'docs/agent-playbook.md', 'CLAUDE.md']) {
      if (!existsSync(join(root, f))) continue;
      for (const m of readFileSync(join(root, f), 'utf8').matchAll(/REQ-[A-Z0-9]+-\d+/g)) {
        if (!defined.has(m[0])) out.push(finding('traceability', 'error', f, `${m[0]} is not defined in any spec`, 'REQ-QA-042'));
      }
    }
    return out;
  },
};

const secrets = {
  id: 'secrets',
  severity: 'error',
  run: ({ root }) => {
    const envPath = join(root, '.env');
    if (!existsSync(envPath)) return [];
    const values = Object.entries(parseEnv(readFileSync(envPath, 'utf8')))
      .filter(([k, v]) => /PASS|SECRET|KEY|TOKEN/.test(k) && v.length >= 6)
      .map(([, v]) => v);
    if (!values.length) return [];
    let tracked;
    try {
      tracked = execFileSync('git', ['ls-files', '-z', '--', ':!core'], { cwd: root, stdio: ['ignore', 'pipe', 'ignore'] }).toString().split('\0').filter(Boolean);
    } catch {
      return [];
    }
    const out = [];
    for (const f of tracked) {
      if (!existsSync(join(root, f))) continue;
      let text;
      try { text = readFileSync(join(root, f), 'utf8'); } catch { continue; }
      if (values.some((v) => text.includes(v))) out.push(finding('secrets', 'error', f, 'file contains a secret value from .env', 'REQ-ARCH-043'));
    }
    return out;
  },
};

export const CHECKS = [coreManifest, layout, phpLint, buildCheck, traceability, secrets];
