/**
 * Vite may emit a dev `index.html` into the outDir; WordPress only enqueues the JS/CSS bundle.
 */
import { unlinkSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const html = path.join(root, 'assets', 'admin', 'react', 'index.html');

try {
  unlinkSync(html);
} catch (err) {
  if (err && err.code !== 'ENOENT') {
    throw err;
  }
}
