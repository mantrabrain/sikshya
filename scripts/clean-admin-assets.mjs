/**
 * Remove the Vite outDir (`assets/admin/react`). Invoked as the first step of `npm run build`
 * so stale hashed chunks cannot linger. Vite's `emptyOutDir` is a second safeguard.
 */
import { rmSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const out = path.join(root, 'assets', 'admin', 'react');

try {
  rmSync(out, { recursive: true, force: true });
} catch (err) {
  if (err && err.code !== 'ENOENT') {
    throw err;
  }
}
