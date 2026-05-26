#!/usr/bin/env node
/**
 * Wrap string literals in toast/dialog/label/option contexts — conservative patterns only.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const pagesDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../client/src/pages');
const skip = new Set(['GenericPlaceholderPage.tsx']);

function skipStr(s) {
  if (!s || s.length < 2) return true;
  if (s.includes('${') || s.includes('{{') || s.includes('%')) return true;
  if (/^https?:\/\//.test(s)) return true;
  if (/^sik_|^sikshya_/.test(s)) return true;
  if (s.startsWith('__(') || s.includes("__('")) return true;
  if (/^[a-z0-9_.]+$/.test(s) && (s.includes('.') || s.includes('_'))) return true; // event keys
  return false;
}

function wrap(s) {
  const escaped = s.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
  return `__('${escaped}', 'sikshya')`;
}

function process(src) {
  let out = src;

  // toast.success('Title', 'msg')
  out = out.replace(/toast\.(success|error)\(\s*'([^']*)'/g, (m, fn, s) =>
    skipStr(s) ? m : `toast.${fn}(${wrap(s)}`
  );
  out = out.replace(/toast\.(success|error)\([^,]+,\s*'([^']*)'/g, (m, fn, s) =>
    skipStr(s) ? m : m.replace(`'${s}'`, wrap(s))
  );

  // confirm({ title: '...', message: '...'
  for (const key of ['title', 'message', 'confirmLabel', 'cancelLabel']) {
    const re = new RegExp(`(${key}:\\s*)'([^']*)'`, 'g');
    out = out.replace(re, (m, pre, s) => (skipStr(s) ? m : `${pre}${wrap(s)}`));
  }

  // throw new Error('...')
  out = out.replace(/throw new Error\(\s*'([^']*)'/g, (m, s) => (skipStr(s) ? m : `throw new Error(${wrap(s)}`));

  // setX('msg') for common message setters - only short UI messages
  out = out.replace(
    /set(?:ManualMsg|SaveMsg|AdvMsg|Msg|Error|PingResult)\(\s*'([^']*)'/g,
    (m, s) => (skipStr(s) ? m : m.replace(`'${s}'`, wrap(s)))
  );

  // <option value="x">Label</option> - wrap label only
  out = out.replace(/<option value="[^"]*">([^<]+)<\/option>/g, (m, label) => {
    const t = label.trim();
    if (skipStr(t)) return m;
    return m.replace(`>${label}<`, `>{${wrap(t)}}<`);
  });

  // label/header in table: <th...>Text</th>
  out = out.replace(/<th([^>]*)>([A-Za-z][^<{]{0,60})<\/th>/g, (m, attrs, t) => {
    const text = t.trim();
    if (skipStr(text)) return m;
    return `<th${attrs}>{${wrap(text)}}</th>`;
  });

  // Button/link text: >{busy ? 'A' : 'B'}<
  out = out.replace(/\? '([^']+)' : '([^']+)'/g, (m, a, b) => {
    if (skipStr(a) && skipStr(b)) return m;
    const wa = skipStr(a) ? `'${a}'` : wrap(a);
    const wb = skipStr(b) ? `'${b}'` : wrap(b);
    return `? ${wa} : ${wb}`;
  });

  // Simple JSX text >Refresh<
  out = out.replace(/>([A-Za-zÀ-ÿ][^<>{}\n]{1,70})</g, (m, t) => {
    const text = t.trim();
    if (skipStr(text) || text.includes('?') || text.includes('&')) return m;
    if (/^\d/.test(text)) return m;
    return `>{${wrap(text)}}<`;
  });

  return out;
}

function walk(dir, out = []) {
  for (const name of fs.readdirSync(dir)) {
    const p = path.join(dir, name);
    if (fs.statSync(p).isDirectory()) walk(p, out);
    else if (name.endsWith('.tsx') && !skip.has(name)) out.push(p);
  }
  return out;
}

for (const file of walk(pagesDir)) {
  const src = fs.readFileSync(file, 'utf8');
  if (!src.includes("lib/i18n")) continue;
  const next = process(src);
  if (next !== src) {
    fs.writeFileSync(file, next);
    console.log('literals:', path.relative(pagesDir, file));
  }
}
