import type { WpPost } from '../types';

/** Prefer canonical course meta; fall back to legacy sample keys. */
export function coursePriceLabel(post: WpPost): string {
  const m = post.meta as Record<string, unknown> | undefined;
  const raw =
    post.sikshya_course_price ??
    m?._sikshya_course_price ??
    m?._sikshya_price ??
    '';
  const s = String(raw).trim();
  if (s === '') {
    return '—';
  }
  const n = parseFloat(s.replace(/[^0-9.-]/g, ''));
  if (!Number.isFinite(n)) {
    return s;
  }
  if (n <= 0) {
    return 'Free';
  }
  return Number.isInteger(n) ? `$${n}` : `$${n.toFixed(2)}`;
}

export function courseMetaString(
  post: WpPost,
  canonicalKey: string,
  legacyKey: string,
  /** Top-level REST field when meta is missing in collections (underscore meta quirk). */
  restFallback?: keyof Pick<WpPost, 'sikshya_course_duration' | 'sikshya_course_level'>
): string {
  if (restFallback) {
    const rf = post[restFallback];
    if (rf != null && String(rf).trim() !== '') {
      return String(rf).trim();
    }
  }
  const m = post.meta as Record<string, unknown> | undefined;
  if (!m) {
    return '—';
  }
  const v = m[canonicalKey] ?? m[legacyKey];
  const s = v != null && String(v).trim() !== '' ? String(v).trim() : '';
  return s || '—';
}

export function embeddedAuthorName(post: WpPost): string {
  const a = post._embedded?.author?.[0];
  if (a && typeof a.name === 'string' && a.name.trim() !== '') {
    return a.name;
  }
  return '—';
}
