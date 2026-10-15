import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';

export const sha256 = (buf) => createHash('sha256').update(buf).digest('hex');
export const sha256File = (path) => sha256(readFileSync(path));
