/**
 * Remove the Vite outDir before `npm run build` so each build starts clean.
 * (Vite's emptyOutDir also clears; this makes the intent obvious from the root package scripts.)
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
