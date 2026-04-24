import { getConfig } from '../config/env';

export function getPermalinkSlug(key: string, fallback: string): string {
  const cfg = getConfig();
  const raw = (cfg.permalinks && (cfg.permalinks as Record<string, unknown>)[key]) || '';
  const s = typeof raw === 'string' ? raw.trim() : '';
  return s || fallback;
}

/**
 * Public certificate URL that matches WP permalink structure:
 * - Pretty: /{certificate_base}/{hash}
 * - Plain:  /{certificate_base}/?hash={hash}
 */
export function certificatePublicUrl(hash: string): string {
  const cfg = getConfig();
  const base = getPermalinkSlug('rewrite_base_certificate', 'certificates').replace(/^\/+|\/+$/g, '');
  const site = cfg.siteUrl.replace(/\/$/, '');
  const clean = String(hash || '').trim();
  if (!clean) return `${site}/${base}/`;
  return cfg.plainPermalinks
    ? `${site}/${base}/?hash=${encodeURIComponent(clean)}`
    : `${site}/${base}/${encodeURIComponent(clean)}`;
}

