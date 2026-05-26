#!/usr/bin/env node
/**
 * Extract admin React/TS strings into languages/sikshya-js.pot (babel makepot).
 *
 * Called from `npm run build` and `scripts/makepot.sh`. Does not merge into sikshya.pot;
 * run `npm run makepot` for the full PHP + JS catalog.
 */
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const pluginDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const babelBin = path.join(pluginDir, 'node_modules', '.bin', 'babel');
const clientSrc = path.join(pluginDir, 'client', 'src');
const jsPot = path.join(pluginDir, 'languages', 'sikshya-js.pot');

if (!fs.existsSync(babelBin)) {
  console.error('extract-admin-js-pot: run npm ci in the plugin root first.');
  process.exit(1);
}

if (!fs.existsSync(clientSrc)) {
  console.warn('extract-admin-js-pot: no client/src — skipping.');
  process.exit(0);
}

const run = spawnSync(
  babelBin,
  [
    'client/src',
    '--extensions',
    '.ts,.tsx,.js,.jsx',
    '--ignore',
    'client/src/**/vite-env.d.ts',
    '--out-file',
    '/dev/null',
  ],
  { cwd: pluginDir, stdio: 'inherit', env: process.env }
);

if (run.status !== 0) {
  process.exit(run.status ?? 1);
}

if (!fs.existsSync(jsPot)) {
  console.warn('extract-admin-js-pot: no strings found — sikshya-js.pot was not created.');
  process.exit(0);
}

const pot = fs.readFileSync(jsPot, 'utf8');
const msgidCount = (pot.match(/^msgid "/gm) || []).length;
console.log(`extract-admin-js-pot: wrote ${jsPot} (${msgidCount} msgid entries, fragment only).`);
