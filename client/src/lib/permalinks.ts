import { getConfig } from '../config/env';

export function getPermalinkSlug(key: string, fallback: string): string {
  const cfg = getConfig();
  const raw = (cfg.permalinks && (cfg.permalinks as Record<string, unknown>)[key]) || '';
  const s = typeof raw === 'string' ? raw.trim() : '';
  return s || fallback;
}

/** Settings → Permalinks: slug fields that get a live “Open” preview link next to the label. */
export const PERMALINK_SETTINGS_PREVIEW_KEYS = new Set([
  'permalink_cart',
  'permalink_checkout',
  'permalink_account',
  'permalink_learn',
  'permalink_order',
  'rewrite_base_course',
  'rewrite_base_lesson',
  'rewrite_base_quiz',
  'rewrite_base_assignment',
  'rewrite_base_certificate',
  'rewrite_base_author',
  'rewrite_tax_course_category',
]);

const VIRTUAL_PAGE_BY_FIELD: Record<string, string> = {
  permalink_cart: 'cart',
  permalink_checkout: 'checkout',
  permalink_account: 'account',
  permalink_learn: 'learn',
  permalink_order: 'order',
};

function siteOrigin(): string {
  return getConfig().siteUrl.replace(/\/$/, '');
}

function slugFromDraft(draft: Record<string, unknown>, key: string): string {
  const v = draft[key];
  const fromDraft = v !== undefined && v !== null ? String(v).trim() : '';
  if (fromDraft !== '') {
    return fromDraft.replace(/^\/+|\/+$/g, '');
  }
  return getPermalinkSlug(key, '').replace(/^\/+|\/+$/g, '');
}

/**
 * Preview URL for Permalink settings fields, using unsaved draft values when present.
 * Mirrors PHP {@see \Sikshya\Services\PermalinkService::virtualPageUrl()} and CPT bases.
 */
export function previewUrlForPermalinkField(fieldKey: string, draft: Record<string, unknown>): string | null {
  const cfg = getConfig();
  const site = siteOrigin();
  const plain = !!cfg.plainPermalinks;

  const virtualPage = VIRTUAL_PAGE_BY_FIELD[fieldKey];
  if (virtualPage) {
    if (plain) {
      return `${site}/?sikshya_page=${encodeURIComponent(virtualPage)}`;
    }
    const slug = slugFromDraft(draft, fieldKey);
    if (!slug) return null;
    return `${site}/${encodeURI(slug)}/`;
  }

  if (fieldKey === 'rewrite_base_certificate') {
    const base = slugFromDraft(draft, 'rewrite_base_certificate') || getPermalinkSlug('rewrite_base_certificate', 'certificates');
    if (!base) return null;
    return `${site}/${encodeURI(base)}/`;
  }

  const pt =
    fieldKey === 'rewrite_base_course'
      ? cfg.postTypes?.course || 'sikshya_course'
      : fieldKey === 'rewrite_base_lesson'
        ? cfg.postTypes?.lesson || 'sikshya_lesson'
        : fieldKey === 'rewrite_base_quiz'
          ? cfg.postTypes?.quiz || 'sikshya_quiz'
          : fieldKey === 'rewrite_base_assignment'
            ? cfg.postTypes?.assignment || 'sikshya_assignment'
            : '';

  if (pt) {
    if (plain || fieldKey !== 'rewrite_base_course') {
      return `${site}/?post_type=${encodeURIComponent(pt)}`;
    }
    const slug = slugFromDraft(draft, fieldKey);
    if (!slug) return null;
    return `${site}/${encodeURI(slug)}/`;
  }

  if (fieldKey === 'rewrite_tax_course_category') {
    const slug = slugFromDraft(draft, 'rewrite_tax_course_category');
    if (!slug) return null;
    return `${site}/${encodeURI(slug)}/`;
  }

  if (fieldKey === 'rewrite_base_author') {
    const base = slugFromDraft(draft, 'rewrite_base_author');
    if (!base) return null;
    const nice = String(cfg.user?.nicename || '').trim();
    if (!nice) {
      return `${site}/${encodeURI(base)}/`;
    }
    return `${site}/${encodeURI(base)}/${encodeURI(nice)}/`;
  }

  return null;
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
