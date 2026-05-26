#!/usr/bin/env node
/**
 * Adds `import { __, sprintf } from '<rel>/lib/i18n';` to page TSX files missing it.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const pagesDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../client/src/pages');
const skip = new Set(['GenericPlaceholderPage.tsx']);

function walk(dir, out = []) {
  for (const name of fs.readdirSync(dir)) {
    const p = path.join(dir, name);
    if (fs.statSync(p).isDirectory()) walk(p, out);
    else if (name.endsWith('.tsx') && !skip.has(name)) out.push(p);
  }
  return out;
}

for (const file of walk(pagesDir)) {
  let src = fs.readFileSync(file, 'utf8');
  if (src.includes("lib/i18n")) continue;

  const relToLib = path.relative(path.dirname(file), path.join(pagesDir, '..', 'lib', 'i18n')).replace(/\\/g, '/');
  const importLine = `import { __, sprintf } from '${relToLib.replace(/\.ts$/, '')}';\n`;

  const m = src.match(/^import .+;\n/m);
  if (!m) {
    src = importLine + src;
  } else {
    const lines = src.split('\n');
    let lastImport = 0;
    for (let i = 0; i < lines.length; i++) {
      if (lines[i].startsWith('import ')) lastImport = i;
    }
    lines.splice(lastImport + 1, 0, importLine.trimEnd());
    src = lines.join('\n') + (src.endsWith('\n') ? '' : '\n');
  }

  fs.writeFileSync(file, src);
  console.log('import added:', path.relative(pagesDir, file));
}
