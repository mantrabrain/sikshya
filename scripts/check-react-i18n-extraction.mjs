#!/usr/bin/env node
/**
 * Verifies client/src string literals used with translate()/t() appear in sikshya-js.pot.
 * __() / _n() / _x() are checked implicitly when makepot runs; this catches the wrapper gap.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const pluginDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const clientSrc = path.join(pluginDir, 'client', 'src');
const jsPot = path.join(pluginDir, 'languages', 'sikshya-js.pot');

const WRAPPER_RE = /\b(?:translate|t)\(\s*['"]([^'"]+)['"]/g;

function walk(dir, out = []) {
  for (const name of fs.readdirSync(dir)) {
    if (name === 'node_modules') continue;
    const p = path.join(dir, name);
    const st = fs.statSync(p);
    if (st.isDirectory()) walk(p, out);
    else if (/\.(tsx?|jsx?)$/.test(name)) out.push(p);
  }
  return out;
}

if (!fs.existsSync(jsPot)) {
  console.error('check-react-i18n-extraction: missing languages/sikshya-js.pot — run npm run extract-js-pot');
  process.exit(1);
}

const pot = fs.readFileSync(jsPot, 'utf8');
const missing = [];

for (const file of walk(clientSrc)) {
  if (file.endsWith(`${path.sep}lib${path.sep}i18n.ts`)) {
    continue;
  }
  const src = fs.readFileSync(file, 'utf8');
  let m;
  WRAPPER_RE.lastIndex = 0;
  while ((m = WRAPPER_RE.exec(src)) !== null) {
    const msgid = m[1];
    if (!pot.includes(`msgid "${msgid.replace(/\\/g, '\\\\').replace(/"/g, '\\"')}"`)) {
      missing.push({ file: path.relative(pluginDir, file), msgid });
    }
  }
}

if (missing.length) {
  console.error('check-react-i18n-extraction: strings missing from sikshya-js.pot:\n');
  for (const { file, msgid } of missing) {
    console.error(`  - "${msgid}" (${file})`);
  }
  console.error('\nEnsure babel.config.cjs lists translate/t under functions, then npm run extract-js-pot');
  process.exit(1);
}

console.log('check-react-i18n-extraction: translate()/t() literals are present in sikshya-js.pot');
