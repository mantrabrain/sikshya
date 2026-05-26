#!/usr/bin/env node
/**
 * Wrap only JSX/HTML string attributes with __() — never touches JSX text or TS generics.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const pagesDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../client/src/pages');
const skipFiles = new Set(['GenericPlaceholderPage.tsx']);

const ATTRS = [
  'title',
  'subtitle',
  'description',
  'placeholder',
  'aria-label',
  'ariaLabel',
  'confirmLabel',
  'featureTitle',
  'featureDescription',
  'addonEnableTitle',
  'addonEnableDescription',
  'emptyMessage',
  'emptyStateTitle',
  'emptyStateDescription',
  'searchPlaceholder',
  'contextHint',
  'message',
  'cancelLabel',
];

function shouldSkipString(s) {
  if (!s || s.length < 2) return true;
  if (s.includes('${') || s.includes('{{') || s.includes('%')) return true;
  if (/^https?:\/\//.test(s)) return true;
  if (/^sik_|^sikshya_/.test(s)) return true;
  if (s.startsWith('__(')) return true;
  return false;
}

function wrapAttr(content, attr) {
  const re = new RegExp(`\\b${attr}="([^"]*)"`, 'g');
  return content.replace(re, (full, str) => {
    if (shouldSkipString(str)) return full;
    const escaped = str.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    return `${attr}={__('${escaped}', 'sikshya')}`;
  });
}

function processFile(file) {
  let src = fs.readFileSync(file, 'utf8');
  if (!src.includes("lib/i18n")) return false;
  const before = src;
  for (const attr of ATTRS) {
    src = wrapAttr(src, attr);
  }
  if (src !== before) {
    fs.writeFileSync(file, src);
    return true;
  }
  return false;
}

function walk(dir, out = []) {
  for (const name of fs.readdirSync(dir)) {
    const p = path.join(dir, name);
    if (fs.statSync(p).isDirectory()) walk(p, out);
    else if (name.endsWith('.tsx') && !skipFiles.has(name)) out.push(p);
  }
  return out;
}

let n = 0;
for (const file of walk(pagesDir)) {
  if (processFile(file)) {
    console.log('attr-wrapped:', path.relative(pagesDir, file));
    n++;
  }
}
console.log(`Done. ${n} files.`);
