// Deterministic file enumeration (SPEC-003 REQ-BLD-002): sorted POSIX relative paths, symlinks rejected.
import { readdirSync, lstatSync } from 'node:fs';
import { join } from 'node:path';

export class SymlinkError extends Error {}

/** @returns {string[]} sorted relative POSIX paths of all files under root (empty if root is missing) */
export function walk(root) {
  const out = [];
  const visit = (abs, rel) => {
    for (const name of readdirSync(abs).sort()) {
      const childAbs = join(abs, name);
      const childRel = rel ? `${rel}/${name}` : name;
      const st = lstatSync(childAbs);
      if (st.isSymbolicLink()) throw new SymlinkError(`symlink not allowed: ${childRel}`);
      if (st.isDirectory()) visit(childAbs, childRel);
      else if (st.isFile()) out.push(childRel);
    }
  };
  try {
    lstatSync(root);
  } catch {
    return out;
  }
  visit(root, '');
  return out.sort();
}
