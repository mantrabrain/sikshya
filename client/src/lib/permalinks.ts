import { getConfig } from '../config/env';

export function getPermalinkSlug(key: string, fallback: string): string {
  const cfg = getConfig();
  const raw = (cfg.permalinks && (cfg.permalinks as Record<string, unknown>)[key]) || '';
  const s = typeof raw === 'string' ? raw.trim() : '';
  return s || fallback;
}

/**
 * Settings → Permalinks: slug fields that get a live "Open" preview link
 * next to the label.
 *
 * Only virtual pages with a real, generically-openable URL (cart, checkout,
 * account, learn hub, order list) plus the two real taxonomy/post-archive
 * bases (course post type + course-category taxonomy) get a preview link.
 *
 * Excluded on purpose:
 *  - Lesson / Quiz / Assignment bases — those post types are not
 *    publicly_queryable; the base only drives the learn-player URL segment
 *    (/learn/<base>/<id>/<slug>), so there is no standalone page to open.
 *  - Certificate base — public certificates open with a unique verification
 *    code (/certificates/<code>/), not the bare base, so a generic preview
 *    would land on a 404.
 *  - Author / instructor base — needs a specific instructor slug appended;
 *    the bare /author/ URL is a WP-core archive and not a Sikshya surface,
 *    so previewing the field by itself doesn't help.
 */
export const PERMALINK_SETTINGS_PREVIEW_KEYS = new Set([
  'permalink_cart',
  'permalink_checkout',
  'permalink_account',
  'permalink_learn',
  'permalink_order',
  'rewrite_base_course',
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

  // Course is the only standalone-CPT URL we still preview; lesson/quiz/
  // assignment bases no longer have public URLs (their post types are
  // registered with publicly_queryable: false — the base only drives the
  // learn-player URL segment), so callers won't see a preview link for
  // those keys (they're filtered out of PERMALINK_SETTINGS_PREVIEW_KEYS).
  if (fieldKey === 'rewrite_base_course') {
    const pt = cfg.postTypes?.course || 'sikshya_course';
    if (plain) {
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
