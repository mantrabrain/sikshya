import type { SikshyaReactConfig } from '../types';

/**
 * Build absolute URL for a Sikshya React subpage (`page=sikshya&view=…`).
 */
export function appViewHref(
  config: SikshyaReactConfig,
  view: string,
  extra: Record<string, string> = {}
): string {
  const fallback =
    typeof window !== 'undefined'
      ? `${window.location.origin}${config.adminUrl.replace(/\/?$/, '/')}admin.php?page=sikshya`
      : `${config.adminUrl.replace(/\/?$/, '/')}admin.php?page=sikshya`;

  const raw = config.appAdminBase || fallback;
  const u = new URL(raw);
  u.searchParams.set('page', 'sikshya');
  u.searchParams.set('view', view);
  Object.entries(extra).forEach(([k, v]) => {
    if (v !== undefined && v !== '') {
      u.searchParams.set(k, v);
    }
  });
  return u.toString();
}
