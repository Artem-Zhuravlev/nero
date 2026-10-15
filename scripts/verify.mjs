// SPEC-009 REQ-QA-001..003: verification runner.
// usage: node scripts/verify.mjs [--only id,id] [--keep-going] [--json]
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { CHECKS } from './lib/checks.mjs';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const WARN_BUDGET = 20;

export function runChecks({ root = ROOT, only, keepGoing = false, phpBin = process.env.PHP_BIN || 'php' } = {}) {
  const selected = CHECKS.filter((c) => !only || only.includes(c.id));
  const results = [];
  for (const c of selected) {
    const t0 = Date.now();
    let findings;
    try {
      findings = c.run({ root, phpBin });
    } catch (e) {
      findings = [{ severity: 'error', check: c.id, path: '', line: 0, message: `check crashed: ${e.message}`, req: '' }];
    }
    const errors = findings.filter((f) => f.severity === 'error').length;
    results.push({ id: c.id, ms: Date.now() - t0, findings, status: errors ? 'FAIL' : findings.length ? 'WARN' : 'PASS' });
    if (errors && !keepGoing) break;
  }
  const warns = results.flatMap((r) => r.findings).filter((f) => f.severity === 'warn').length;
  const failed = results.some((r) => r.status === 'FAIL') || warns >= WARN_BUDGET;
  return { results, failed, warns };
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  const a = process.argv.slice(2);
  const onlyIdx = a.indexOf('--only');
  const only = onlyIdx >= 0 ? a[onlyIdx + 1].split(',') : undefined;
  const { results, failed } = runChecks({ only, keepGoing: a.includes('--keep-going') });
  if (a.includes('--json')) {
    console.log(JSON.stringify(results.flatMap((r) => r.findings), null, 2));
  } else {
    for (const r of results) {
      console.log(`${r.status} ${r.id} ${r.ms}ms`);
      for (const f of r.findings.slice(0, 50)) console.log(`  ${f.severity} ${f.check} ${f.path}${f.line ? `:${f.line}` : ''} ${f.message}${f.req ? ` [${f.req}]` : ''}`);
    }
    console.log(failed ? 'verify: FAILED' : 'verify: OK');
  }
  process.exit(failed ? 1 : 0);
}
