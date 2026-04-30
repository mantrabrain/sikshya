/**
 * Lightweight i18n wrapper.
 *
 * Sikshya's admin SPA can run in contexts where the WordPress i18n runtime
 * is present (`window.wp.i18n`) but script translations may not be wired up yet.
 * This helper keeps callsites consistent while we progressively translate UI.
 */
export function t(msg: string, domain = 'sikshya'): string {
  try {
    const wp = (window as any)?.wp;
    const i18n = wp?.i18n;
    const __fn = typeof i18n?.__ === 'function' ? (i18n.__ as (m: string, d?: string) => string) : null;
    if (__fn) {
      return __fn(msg, domain);
    }
  } catch {
    // fall through
  }
  return msg;
}

