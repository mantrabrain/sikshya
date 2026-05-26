#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const pagesDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../client/src/pages');

function walk(dir, out = []) {
  for (const name of fs.readdirSync(dir)) {
    const p = path.join(dir, name);
    if (fs.statSync(p).isDirectory()) walk(p, out);
    else if (/\.tsx?$/.test(name)) out.push(p);
  }
  return out;
}

function fix(src) {
  let s = src;
  // Broken >= from JSX text wrap
  s = s.replace(/>\{__\('= /g, '>=');
  s = s.replace(/\}\{__\('= /g, '} = ');
  s = s.replace(/:\s*Record', 'sikshya'\)\}</g, ': Record<');
  s = s.replace(/Record', 'sikshya'\)\}</g, 'Record<');
  s = s.replace(/Array', 'sikshya'\)\}</g, 'Array<');
  s = s.replace(/Column', 'sikshya'\)\}</g, 'Column<');
  s = s.replace(/ReadonlyArray', 'sikshya'\)\}</g, 'ReadonlyArray<');
  s = s.replace(/:\s*Array', 'sikshya'\)\}</g, ': Array<');
  s = s.replace(/filter\(\(row\): row is Record', 'sikshya'\)\}</g, 'filter((row): row is Record<');
  s = s.replace(/onChangeGuard', 'sikshya'\)\}</g, 'onChange');
  s = s.replace(/onChange=\{\(e\) =>\{__\('onChangeGuard', 'sikshya'\)\}</g, 'onChange={(e) =>');
  // Escaped quotes from bad wrap
  s = s.replace(/\\'/g, "'");
  s = s.replace(/__\\\(/g, "__('");
  return s;
}

for (const file of walk(pagesDir)) {
  const before = fs.readFileSync(file, 'utf8');
  const after = fix(before);
  if (after !== before) {
    fs.writeFileSync(file, after);
    console.log('fixed:', path.relative(pagesDir, file));
  }
}
