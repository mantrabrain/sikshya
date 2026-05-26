/**
 * WordPress i18n for the Sikshya admin SPA.
 *
 * Import `__`, `_n()`, `_x()`, `sprintf()` (or `translate` / `t` with string literals) from
 * this module. Babel makepot scans `client/src` on `npm run build` / `npm run makepot` and
 * writes `languages/sikshya-js.pot` (merged into `sikshya.pot` by makepot.sh).
 *
 * Runtime: requires `wp-i18n` + `wp_set_script_translations` on `sikshya-react-admin`
 * (see `AdminAssetsService`).
 *
 * Extraction rules:
 * - Only string **literals** at the call site are picked up, e.g. `__('Save', 'sikshya')`.
 * - `translate('Save')` works when `translate` is listed in `babel.config.cjs` `functions`.
 * - Do not pass dynamic variables: `translate(label)` will not appear in the .pot file.
 */
import { __, _n, _x, sprintf } from '@wordpress/i18n';

export { __, _n, _x, sprintf };

/**
 * Same as `__('text', 'sikshya')` but returns a plain `string` (fixes strict TS unions).
 * Use a string literal so makepot can extract the msgid.
 */
export function translate(text: string, domain = 'sikshya'): string {
  // eslint-disable-next-line @wordpress/i18n-text-domain -- domain is parameterized for legacy callers.
  return __(text, domain) as string;
}

/** @deprecated Prefer `__()` or `translate()` with a string literal. */
export function t(msg: string, domain = 'sikshya'): string {
  return translate(msg, domain);
}
