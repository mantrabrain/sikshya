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

/**
 * Course list / admin: `_sikshya_duration` is estimated length in hours (Course details).
 * Raw REST/meta often returns a bare number ("8"); append a unit so the table matches learner-facing copy.
 */
export function formatCourseDurationListCell(raw: string): string {
  const s = raw.trim();
  if (!s || s === '—') {
    return '—';
  }

  // Already labeled (matches catalog / legacy free-form strings).
  if (/[a-z]/i.test(s) && /(h|hour|m|min)/i.test(s)) {
    return s;
  }

  // "1:30" style (hours : minutes), aligned with `sikshya_format_course_duration_display` on the frontend.
  const hm = /^(\d{1,3})\s*:\s*(\d{1,2})$/.exec(s);
  if (hm) {
    const h = parseInt(hm[1], 10);
    const mm = parseInt(hm[2], 10);
    if (h <= 0 && mm <= 0) {
      return '—';
    }
    if (h > 0 && mm > 0) {
      return `${h}h ${mm}m`;
    }
    if (h > 0) {
      return `${h}h`;
    }
    return `${mm}m`;
  }

  // Plain number → hours (Course Builder "Estimated length (hours)").
  if (/^\d+(\.\d+)?$/.test(s)) {
    const n = parseFloat(s);
    if (!Number.isFinite(n) || n <= 0) {
      return '—';
    }
    const disp = Number.isInteger(n) ? String(Math.trunc(n)) : String(n);
    return `${disp} h`;
  }

  return s;
}

export function embeddedAuthorName(post: WpPost): string {
  const a = post._embedded?.author?.[0];
  if (a && typeof a.name === 'string' && a.name.trim() !== '') {
    return a.name;
  }
  return '—';
}
