import type { SikshyaReactConfig, WpPost } from '../types';

/** Mirrors {@see \\Sikshya\\Services\\PermalinkService::QUERY_VAR}. */
const Q_PAGE = 'sikshya_page';
/** Mirrors {@see \\Sikshya\\Services\\PermalinkService::LEARN_TYPE_VAR}. */
const Q_LEARN_TYPE = 'sikshya_learn_type';
/** Mirrors {@see \\Sikshya\\Services\\PermalinkService::LEARN_SLUG_VAR}. */
const Q_LEARN_SLUG = 'sikshya_learn_slug';
/** Mirrors {@see \\Sikshya\\Services\\PermalinkService::LEARN_PUBLIC_ID_VAR}. */
const Q_LEARN_PUBLIC_ID = 'sikshya_learn_public_id';

function siteHome(siteUrl: string): string {
  return siteUrl.replace(/\/$/, '');
}

function trailingSlash(url: string): string {
  return url.endsWith('/') ? url : `${url}/`;
}

function permalinkSlug(cfg: SikshyaReactConfig, optionKey: string, fallback: string): string {
  const raw = cfg.permalinks && (cfg.permalinks as Record<string, unknown>)[optionKey];
  const s = typeof raw === 'string' ? raw.trim() : '';
  const trimmed = (s || fallback).replace(/^\/+|\/+$/g, '');
  return trimmed || fallback;
}

function learnUsesPublicId(cfg: SikshyaReactConfig): boolean {
  return cfg.learnUsesPublicId !== false;
}

function readWpMeta(meta: Record<string, unknown> | undefined, dbKey: string): unknown {
  if (!meta) {
    return undefined;
  }
  const noUnderscore = dbKey.startsWith('_') ? dbKey.slice(1) : dbKey;
  return meta[dbKey] ?? meta[noUnderscore] ?? meta[`_${noUnderscore}`];
}

/**
 * Learn player URL for a lesson/quiz/assignment — matches PHP {@see PublicPageUrls::learnContent()}
 * including optional public-id segment and plain permalink query mode.
 */
export function buildLearnShellContentUrl(
  cfg: SikshyaReactConfig,
  opts: { type: 'lesson' | 'quiz' | 'assignment'; slug: string; publicId?: string | null }
): string | null {
  const { type } = opts;
  const slug = String(opts.slug || '').trim().replace(/^\/+|\/+$/g, '');
  const usePidSetting = learnUsesPublicId(cfg);
  let publicId =
    opts.publicId != null && String(opts.publicId).trim() !== '' ? String(opts.publicId).trim() : '';
  if (usePidSetting && publicId !== '') {
    publicId = publicId.replace(/[^A-Za-z0-9]/g, '');
  } else {
    publicId = '';
  }

  if (!slug) {
    return null;
  }

  const home = siteHome(cfg.siteUrl || '');
  const plain = !!cfg.plainPermalinks;

  if (plain) {
    const u = new URL(`${home}/`);
    u.searchParams.set(Q_PAGE, 'learn');
    u.searchParams.set(Q_LEARN_TYPE, type);
    u.searchParams.set(Q_LEARN_SLUG, slug);
    if (usePidSetting && publicId !== '') {
      u.searchParams.set(Q_LEARN_PUBLIC_ID, publicId);
    }
    return u.toString();
  }

  const learnSlug = permalinkSlug(cfg, 'permalink_learn', 'learn');
  const base = `${home}/${encodeURIComponent(learnSlug)}`.replace(/\/?$/, '');
  const segType = encodeURIComponent(type);
  const segSlug = encodeURIComponent(slug);
  if (usePidSetting && publicId !== '') {
    return trailingSlash(`${base}/${segType}/${encodeURIComponent(publicId)}/${segSlug}`);
  }
  return trailingSlash(`${base}/${segType}/${segSlug}`);
}

/** Learn hub with {@see PublicPageUrls::learnForCourse()}. */
export function buildLearnForCourseUrl(cfg: SikshyaReactConfig, courseId: number): string | null {
  if (!(courseId > 0)) {
    return null;
  }
  const home = siteHome(cfg.siteUrl || '');
  if (cfg.plainPermalinks) {
    const u = new URL(`${home}/`);
    u.searchParams.set(Q_PAGE, 'learn');
    u.searchParams.set('course_id', String(courseId));
    return u.toString();
  }
  const learnSlug = permalinkSlug(cfg, 'permalink_learn', 'learn');
  const base = trailingSlash(`${home}/${encodeURIComponent(learnSlug)}`);
  return `${base}?course_id=${encodeURIComponent(String(courseId))}`;
}

/** Client fallback when `sikshya_learn_view_url` REST field is empty (still published). */
export function contentLibraryLearnViewFallback(cfg: SikshyaReactConfig, restBase: string, r: WpPost): string | null {
  if (restBase !== 'sik_lesson' && restBase !== 'sik_quiz' && restBase !== 'sik_assignment') {
    return null;
  }
  if (r.status !== 'publish') {
    return null;
  }
  const type = restBase === 'sik_lesson' ? 'lesson' : restBase === 'sik_quiz' ? 'quiz' : 'assignment';
  const slug = String(r.slug || '').trim();
  if (!slug) {
    return null;
  }
  const meta = r.meta as Record<string, unknown> | undefined;
  const rawPid = readWpMeta(meta, '_sikshya_learn_public_id');
  const publicId = rawPid != null && String(rawPid).trim() !== '' ? String(rawPid) : '';

  return buildLearnShellContentUrl(cfg, { type, slug, publicId });
}

export function contentLibraryChapterLearnFallback(cfg: SikshyaReactConfig, r: WpPost): string | null {
  if (r.status !== 'publish') {
    return null;
  }
  const meta = r.meta as Record<string, unknown> | undefined;
  const cid = Number(readWpMeta(meta, '_sikshya_chapter_course_id') ?? 0);

  return buildLearnForCourseUrl(cfg, cid);
}
