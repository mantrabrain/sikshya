import type { SikshyaReactConfig } from '../types';

/**
 * Build absolute URL for a Sikshya React subpage (`page=sikshya&view=…`).
 */
export function appViewHref(
  config: SikshyaReactConfig,
  view: string,
  extra: Record<string, string> = {}
): string {
  const adminBase =
    typeof config.adminUrl === 'string' && config.adminUrl.trim() !== ''
      ? config.adminUrl.trim()
      : '/wp-admin/';
  const fallback =
    typeof window !== 'undefined'
      ? `${window.location.origin}${adminBase.replace(/\/?$/, '/')}admin.php?page=sikshya`
      : `${adminBase.replace(/\/?$/, '/')}admin.php?page=sikshya`;

  const raw =
    typeof config.appAdminBase === 'string' && config.appAdminBase.trim() !== ''
      ? config.appAdminBase.trim()
      : fallback;
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
