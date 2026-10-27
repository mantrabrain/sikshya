#!/usr/bin/env node
/**
 * Fails when a `<label>` in the admin client names no form control.
 *
 * `<label>Expiry (days)</label><input type="number" />` looks labelled and is
 * not: with no `htmlFor` and the control outside the label, the two elements
 * are unrelated, so a screen reader announces "edit, blank" and voice control
 * has nothing to aim at. It is the single most common accessibility defect in
 * this codebase and the easiest to reintroduce, because the mistake is
 * invisible on screen.
 *
 * The browser gate (`e2e/tests/a11y/`) only sees a screen's landing state, so
 * a field behind a tab, an accordion or a modal is never scanned. This reads
 * the source instead and therefore sees all of them.
 *
 * A `<label>` wrapping a *component* (`<QuillField>`, `<DateTimePickerField>`)
 * is reported too, and that is not a false positive: those components render
 * their own label, so the outer one names nothing and turns every click inside
 * them into a click on a label. Pass the text to the component instead.
 *
 * Run: `npm run a11y:labels`
 */
import { readFileSync } from 'node:fs';
import { execSync } from 'node:child_process';
import path from 'node:path';

const ROOT = path.resolve(import.meta.dirname, '..');

/**
 * Blank out comments and string/template literals before matching, so that a
 * `<label>` mentioned in a comment or in prose is not reported as markup.
 * Replaced with spaces of the same length to keep line numbers honest.
 */
function blankNonCode(src) {
  const blank = (s) => s.replace(/[^\n]/g, ' ');

  return src.replace(
    /\/\*[\s\S]*?\*\/|\/\/[^\n]*|`(?:\\[\s\S]|[^`\\])*`|'(?:\\[\s\S]|[^'\\\n])*'|"(?:\\[\s\S]|[^"\\\n])*"/g,
    blank
  );
}

const CONTROL = /<(input|select|textarea)\b/;

function orphanLabels(file) {
  const src = blankNonCode(readFileSync(path.join(ROOT, file), 'utf8'));
  const found = [];
  const re = /<label[\s>]/g;
  let m;

  while ((m = re.exec(src))) {
    const tagEnd = src.indexOf('>', m.index);
    if (tagEnd === -1) continue;

    const tag = src.slice(m.index, tagEnd + 1);
    // An explicit association, or an `aria-label`/`aria-labelledby` elsewhere
    // doing the naming, is fine.
    if (/\bhtmlFor\b/.test(tag)) continue;

    const close = src.indexOf('</label>', tagEnd);
    const body = close === -1 ? '' : src.slice(tagEnd, close);
    // A control nested inside the label is associated implicitly.
    if (CONTROL.test(body)) continue;

    found.push(src.slice(0, m.index).split('\n').length);
  }

  return found;
}

const files = execSync("find client/src -name '*.tsx' -type f", { cwd: ROOT, encoding: 'utf8' })
  .trim()
  .split('\n')
  .filter(Boolean)
  .sort();

let total = 0;
for (const file of files) {
  for (const line of orphanLabels(file)) {
    console.log(`${file}:${line}: <label> names no control (add htmlFor, or nest the control inside it)`);
    total++;
  }
}

if (total > 0) {
  console.error(`\n${total} unassociated <label>(s). Each one is a form field with no accessible name.`);
  process.exit(1);
}

console.log(`a11y:labels — ${files.length} file(s) checked, every <label> names a control.`);
