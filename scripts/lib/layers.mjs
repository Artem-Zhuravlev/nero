// Layer collection and collision detection (SPEC-003 REQ-BLD-001..003, 010, 011, 020, 021, 033)
import { existsSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { walk } from './fs-walk.mjs';

export const GENERATED = (adminDir) => ['config.php', `${adminDir}/config.php`, '.htaccess'];
export const RUNTIME_DIRS = ['image/catalog/', 'image/cache/', 'image/data/'];
const OVERLAY_REPLACEABLE = new Set(['robots.txt']);

const isCoreExcluded = (rel) =>
  rel.startsWith('install/') ||
  rel.startsWith('system/storage/') ||
  ['config-dist.php', 'admin/config-dist.php', '.htaccess.txt', 'php.ini'].includes(rel);

const isCustomExcluded = (rel) => rel.split('/').pop() === '.gitkeep' || rel.split('/').includes('tests');

const rewriteAdmin = (rel, adminDir) => (adminDir !== 'admin' && rel.startsWith('admin/') ? `${adminDir}/${rel.slice(6)}` : rel);

const RESERVED_WIN = /^(con|prn|aux|nul|com[1-9]|lpt[1-9])(\..*)?$/i;

/**
 * @returns {{files: Array<{rel:string, abs:string, layer:string}>, collisions: string[]}}
 */
export function collectLayers({ coreDir, customDir, adminDir }) {
  const byKey = new Map();
  const collisions = [];

  const add = (rel, abs, layer) => {
    if (rel.split('/').some((seg) => RESERVED_WIN.test(seg))) {
      collisions.push(`INVALID ${rel} (${layer}): reserved file name`);
      return;
    }
    const key = rel.toLowerCase();
    const prev = byKey.get(key);
    if (prev) {
      if (layer === 'overlay' && prev.layer === 'core' && OVERLAY_REPLACEABLE.has(rel)) {
        byKey.set(key, { rel, abs, layer });
        return;
      }
      collisions.push(`COLLISION ${rel} (${prev.layer} vs ${layer})`);
      return;
    }
    byKey.set(key, { rel, abs, layer });
  };

  const coreUpload = join(coreDir, 'upload');
  for (const rel of walk(coreUpload)) {
    if (isCoreExcluded(rel)) continue;
    add(rewriteAdmin(rel, adminDir), join(coreUpload, rel), 'core');
  }

  const overlayDir = join(customDir, 'overlay');
  for (const rel of walk(overlayDir)) {
    if (isCustomExcluded(rel)) continue;
    add(rewriteAdmin(rel, adminDir), join(overlayDir, rel), 'overlay');
  }

  // SPEC-053: OCMOD ships as system/<code>.ocmod.xml, which core's modification refresh applies straight from disk.
  const ocmodDir = join(customDir, 'ocmod');
  for (const rel of walk(ocmodDir)) {
    if (rel.endsWith('.ocmod.xml') && !rel.includes('/')) add(`system/${rel}`, join(ocmodDir, rel), 'ocmod');
  }

  const themeDir = join(customDir, 'theme', 'ocs');
  for (const rel of walk(themeDir)) {
    if (isCustomExcluded(rel)) continue;
    add(`catalog/view/theme/ocs/${rel}`, join(themeDir, rel), 'theme');
  }

  const extRoot = join(customDir, 'extensions');
  if (existsSync(extRoot)) {
    for (const ext of readdirSync(extRoot).sort()) {
      const upload = join(extRoot, ext, 'upload');
      if (!existsSync(upload) || !statSync(upload).isDirectory()) continue;
      for (const rel of walk(upload)) {
        if (isCustomExcluded(rel)) continue;
        add(rewriteAdmin(rel, adminDir), join(upload, rel), `ext:${ext}`);
      }
    }
  }

  for (const g of GENERATED(adminDir)) {
    const hit = byKey.get(g.toLowerCase());
    if (hit) collisions.push(`COLLISION ${g} (${hit.layer} vs generated)`);
  }

  const files = [...byKey.values()].sort((a, b) => (a.rel < b.rel ? -1 : a.rel > b.rel ? 1 : 0));
  return { files, collisions };
}
