// .env parsing and validation (SPEC-001 section 7, SPEC-003 REQ-BLD-020/035)
import { readFileSync, existsSync } from 'node:fs';

export class EnvError extends Error {}

const REQUIRED = ['APP_ENV', 'APP_URL', 'ADMIN_DIR', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PREFIX', 'STORAGE_DIR'];
const RESERVED_ADMIN = new Set(['catalog', 'system', 'image', 'install', 'index', 'config', 'custom', 'core', 'build', 'var']);
const ENVS = new Set(['local', 'staging', 'production']);

export function parseEnv(text) {
  const out = {};
  for (const raw of text.split(/\r?\n/)) {
    const line = raw.trim();
    if (!line || line.startsWith('#')) continue;
    const i = line.indexOf('=');
    if (i < 1) throw new EnvError(`invalid line in env file: ${line}`);
    let v = line.slice(i + 1).trim();
    if ((v.startsWith('"') && v.endsWith('"')) || (v.startsWith("'") && v.endsWith("'"))) v = v.slice(1, -1);
    out[line.slice(0, i).trim()] = v;
  }
  return out;
}

export function loadEnvFile(path) {
  if (!existsSync(path)) throw new EnvError(`env file not found: ${path}`);
  return parseEnv(readFileSync(path, 'utf8'));
}

/** Validates and returns a normalised copy. Throws EnvError naming the first problem. */
export function validateEnv(env) {
  for (const k of REQUIRED) {
    if (env[k] === undefined || env[k] === '') throw new EnvError(`missing required env value: ${k}`);
  }
  if (!ENVS.has(env.APP_ENV)) throw new EnvError(`APP_ENV must be one of ${[...ENVS].join('|')}`);
  if (env.DB_PASS === undefined || (env.DB_PASS === '' && env.APP_ENV !== 'local')) {
    throw new EnvError('missing required env value: DB_PASS (empty allowed only when APP_ENV=local)');
  }
  if (!/^https?:\/\/[^\s/]+(\/[^\s]*)?\/$/.test(env.APP_URL)) {
    throw new EnvError('APP_URL must be an absolute http(s) URL ending with "/"');
  }
  if (env.APP_ENV === 'production' && !env.APP_URL.startsWith('https://')) {
    throw new EnvError('APP_URL must be https in production');
  }
  if (env.ADMIN_DIR !== 'admin' && !/^[a-z0-9][a-z0-9_-]{2,31}$/.test(env.ADMIN_DIR)) {
    throw new EnvError('ADMIN_DIR must match ^[a-z0-9][a-z0-9_-]{2,31}$');
  }
  if (env.ADMIN_DIR !== 'admin' && RESERVED_ADMIN.has(env.ADMIN_DIR)) {
    throw new EnvError(`ADMIN_DIR "${env.ADMIN_DIR}" is reserved`);
  }
  if (!/^\d+$/.test(env.DB_PORT)) throw new EnvError('DB_PORT must be numeric');
  if (!/^[A-Za-z0-9_]+$/.test(env.DB_PREFIX)) throw new EnvError('DB_PREFIX must match [A-Za-z0-9_]+');
  return { ...env };
}
