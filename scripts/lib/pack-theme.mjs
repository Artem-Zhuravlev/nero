// SPEC-040: compose the installable theme package (.ocmod.zip) from custom/ + the tooling PHP library.
import { readFileSync, existsSync, readdirSync, statSync, mkdirSync, writeFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { deflateRawSync } from 'node:zlib';
import { walk } from './fs-walk.mjs';
import { demoImages, referencedImages } from './demo-images.mjs';

/** Directories the core installer (admin/controller/marketplace/install.php, move()) accepts below upload/. */
export const ALLOWED_PREFIXES = [
  'admin/controller/extension/', 'admin/language/', 'admin/model/extension/', 'admin/view/image/', 'admin/view/javascript/',
  'admin/view/stylesheet/', 'admin/view/template/extension/', 'catalog/controller/extension/', 'catalog/language/',
  'catalog/model/extension/', 'catalog/view/javascript/', 'catalog/view/theme/', 'system/config/', 'system/library/', 'image/catalog/',
];

const SETUP_DIR = 'system/library/ocs/setup/';
const isExcluded = (rel) => rel.split('/').pop() === '.gitkeep' || rel.split('/').includes('tests');
const SKIP_THEME = new Set(['DESIGN.md']);

export class PackError extends Error {}

/** @returns {Map<string, Buffer>} zip entry path -> content */
export function collectPackage({ root }) {
  const custom = join(root, 'custom');
  const files = new Map();
  const add = (path, content, origin) => {
    if (files.has(path)) throw new PackError(`COLLISION ${path} (${origin})`);
    files.set(path, Buffer.isBuffer(content) ? content : Buffer.from(content));
  };

  const themeDir = join(custom, 'theme', 'ocs');
  for (const rel of walk(themeDir)) {
    if (isExcluded(rel) || SKIP_THEME.has(rel)) continue;
    add(`upload/catalog/view/theme/ocs/${rel}`, readFileSync(join(themeDir, rel)), 'theme');
  }

  const extRoot = join(custom, 'extensions');
  const codes = existsSync(extRoot) ? readdirSync(extRoot).filter((c) => statSync(join(extRoot, c)).isDirectory()).sort() : [];
  for (const code of codes) {
    const upload = join(extRoot, code, 'upload');
    for (const rel of walk(upload)) {
      if (isExcluded(rel)) continue;
      add(`upload/${rel}`, readFileSync(join(upload, rel)), `ext:${code}`);
    }
    // Migrations travel as payload: the in-store setup runs the same runner as `npm run db:migrate`.
    const payload = `upload/${SETUP_DIR}payload/custom/extensions/${code}/`;
    if (existsSync(join(extRoot, code, 'extension.json'))) add(`${payload}extension.json`, readFileSync(join(extRoot, code, 'extension.json')), `ext:${code}`);
    for (const rel of walk(join(extRoot, code, 'db'))) add(`${payload}db/${rel}`, readFileSync(join(extRoot, code, 'db', rel)), `ext:${code}`);
  }

  const payloadRoot = `upload/${SETUP_DIR}payload/`;
  for (const rel of walk(join(custom, 'db', 'migrations'))) {
    if (!isExcluded(rel)) add(`${payloadRoot}custom/db/migrations/${rel}`, readFileSync(join(custom, 'db', 'migrations', rel)), 'migrations');
  }
  for (const rel of walk(join(custom, 'ocmod'))) {
    if (!isExcluded(rel)) add(`${payloadRoot}custom/ocmod/${rel}`, readFileSync(join(custom, 'ocmod', rel)), 'ocmod');
  }
  // SPEC-041: demo catalogue data (payload) and generated placeholder pictures (docroot image/).
  const catalogueFile = join(custom, 'catalog', 'demo', 'catalogue.json');
  if (!existsSync(catalogueFile)) throw new PackError('custom/catalog/demo/catalogue.json is missing (run scripts/gen-demo-catalogue.mjs)');
  const catalogueJson = readFileSync(catalogueFile);
  add(`${payloadRoot}custom/catalog/demo/catalogue.json`, catalogueJson, 'demo-catalogue');
  const pictures = demoImages(JSON.parse(catalogueJson.toString('utf8')));
  for (const [rel, buf] of pictures) add(`upload/image/${rel}`, buf, 'demo-images');
  const missing = referencedImages(JSON.parse(catalogueJson.toString('utf8'))).filter((rel) => !pictures.has(rel));
  if (missing.length) throw new PackError(`demo catalogue references missing images: ${missing.slice(0, 10).join(', ')}`);

  add(`${payloadRoot}VERSION`, `${version(root)}\n`, 'version');

  const lib = join(root, 'scripts', 'lib', 'php');
  add(`upload/${SETUP_DIR}autoload.php`, readFileSync(join(lib, 'autoload.php')), 'setup-lib');
  for (const dir of ['Migrate', 'Setup']) {
    for (const rel of walk(join(lib, dir))) add(`upload/${SETUP_DIR}${dir}/${rel}`, readFileSync(join(lib, dir, rel)), 'setup-lib');
  }

  // SPEC-053 REQ-OCM-006: the installer cannot write to system/, so plain copies sit in the archive root for a manual copy into system/.
  for (const rel of walk(join(custom, 'ocmod'))) {
    if (rel.endsWith('.ocmod.xml') && !rel.includes('/')) add(`ocmod-xml/${rel}`, readFileSync(join(custom, 'ocmod', rel)), 'ocmod-xml');
  }

  add('README-INSTALL.md', readFileSync(join(root, 'docs', 'theme-install.md')), 'docs');
  return files;
}

export function version(root) {
  const f = join(root, 'custom', 'VERSION');
  if (!existsSync(f)) throw new PackError('custom/VERSION is missing');
  const v = readFileSync(f, 'utf8').trim();
  if (!/^\d+\.\d+\.\d+$/.test(v)) throw new PackError(`custom/VERSION must be MAJOR.MINOR.PATCH, got '${v}'`);
  return v;
}

/** REQ-PKG-002: every upload/ path must be accepted by the core installer. @returns {string[]} offending paths */
export function disallowedPaths(files) {
  const bad = [];
  for (const path of files.keys()) {
    if (!path.startsWith('upload/')) continue;
    const dest = path.slice('upload/'.length);
    if (!ALLOWED_PREFIXES.some((p) => dest.startsWith(p)) || dest.split('/').includes('..')) bad.push(path);
  }
  return bad;
}

const SECRET_PATTERNS = [
  [/-----BEGIN [A-Z ]*PRIVATE KEY-----/, 'private key'],
  [/\b[A-Za-z]:[\\/](?:Users|open-cart|Windows)[\\/]/, 'absolute Windows path'],
  [/\/(?:home|Users)\/[a-z0-9_.-]+\//i, 'absolute home path'],
  [/\b[A-Za-z0-9._%+-]+@(?:gmail|yahoo|outlook|hotmail|mail)\.[a-z.]+\b/i, 'personal e-mail address'],
  [/\b(?:sk|pk|rk)_(?:live|test)_[A-Za-z0-9]{16,}/, 'payment API key'],
  [/\bAKIA[0-9A-Z]{16}\b/, 'AWS access key'],
];

/** REQ-PKG-020. @returns {string[]} findings */
export function scanPackage(files, envValues = []) {
  const out = [];
  for (const [path, buf] of files) {
    if (/\.(woff2?|png|jpe?g|gif|webp|ico|svg)$/i.test(path)) continue;
    const text = buf.toString('utf8');
    for (const [re, label] of SECRET_PATTERNS) if (re.test(text)) out.push(`${path}: ${label}`);
    for (const v of envValues) if (v && text.includes(v)) out.push(`${path}: contains a value from .env`);
  }
  return out;
}

// ---- minimal deterministic zip writer (store/deflate, no zip64, no dependencies) ----
const CRC_TABLE = (() => {
  const t = new Uint32Array(256);
  for (let n = 0; n < 256; n++) {
    let c = n;
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    t[n] = c >>> 0;
  }
  return t;
})();

export function crc32(buf) {
  let c = 0xffffffff;
  for (let i = 0; i < buf.length; i++) c = CRC_TABLE[(c ^ buf[i]) & 0xff] ^ (c >>> 8);
  return (c ^ 0xffffffff) >>> 0;
}

const DOS_TIME = 0;
const DOS_DATE = ((2024 - 1980) << 9) | (1 << 5) | 1; // 2024-01-01, fixed for reproducible archives

/** @param {Map<string, Buffer>} files @returns {Buffer} */
export function zip(files) {
  const parts = [];
  const central = [];
  let offset = 0;
  for (const name of [...files.keys()].sort()) {
    const data = files.get(name);
    const nameBuf = Buffer.from(name, 'utf8');
    const deflated = deflateRawSync(data, { level: 9 });
    const useDeflate = deflated.length < data.length;
    const body = useDeflate ? deflated : data;
    const method = useDeflate ? 8 : 0;
    const crc = crc32(data);

    const local = Buffer.alloc(30);
    local.writeUInt32LE(0x04034b50, 0);
    local.writeUInt16LE(20, 4);
    local.writeUInt16LE(0x0800, 6); // UTF-8 names
    local.writeUInt16LE(method, 8);
    local.writeUInt16LE(DOS_TIME, 10);
    local.writeUInt16LE(DOS_DATE, 12);
    local.writeUInt32LE(crc, 14);
    local.writeUInt32LE(body.length, 18);
    local.writeUInt32LE(data.length, 22);
    local.writeUInt16LE(nameBuf.length, 26);
    local.writeUInt16LE(0, 28);
    parts.push(local, nameBuf, body);

    const cen = Buffer.alloc(46);
    cen.writeUInt32LE(0x02014b50, 0);
    cen.writeUInt16LE(20, 4);
    cen.writeUInt16LE(20, 6);
    cen.writeUInt16LE(0x0800, 8);
    cen.writeUInt16LE(method, 10);
    cen.writeUInt16LE(DOS_TIME, 12);
    cen.writeUInt16LE(DOS_DATE, 14);
    cen.writeUInt32LE(crc, 16);
    cen.writeUInt32LE(body.length, 20);
    cen.writeUInt32LE(data.length, 24);
    cen.writeUInt16LE(nameBuf.length, 28);
    cen.writeUInt32LE(0, 38);
    cen.writeUInt32LE(offset, 42);
    central.push(cen, nameBuf);
    offset += local.length + nameBuf.length + body.length;
  }
  const cenBuf = Buffer.concat(central);
  const end = Buffer.alloc(22);
  end.writeUInt32LE(0x06054b50, 0);
  end.writeUInt16LE(files.size, 8);
  end.writeUInt16LE(files.size, 10);
  end.writeUInt32LE(cenBuf.length, 12);
  end.writeUInt32LE(offset, 16);
  return Buffer.concat([...parts, cenBuf, end]);
}

/**
 * @returns {{file:string, files:number, bytes:number, version:string}}
 */
export function packTheme({ root, outDir, envValues = [] }) {
  const files = collectPackage({ root });
  const bad = disallowedPaths(files);
  if (bad.length) throw new PackError(`paths the core installer would reject:\n  ${bad.slice(0, 20).join('\n  ')}`);
  const findings = scanPackage(files, envValues);
  if (findings.length) throw new PackError(`package scan failed:\n  ${findings.join('\n  ')}`);
  const v = version(root);
  const buf = zip(files);
  mkdirSync(outDir, { recursive: true });
  const file = join(outDir, `ocs-theme-${v}.ocmod.zip`);
  writeFileSync(file, buf);
  return { file, files: files.size, bytes: buf.length, version: v };
}

export { dirname };
