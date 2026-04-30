import type { WpPost } from '../types';

/** Pick the first usable image URL from REST media `sizes`. */
function firstSizeUrl(sizes: unknown): string | null {
  if (!sizes || typeof sizes !== 'object') {
    return null;
  }
  const preferred = ['medium', 'large', 'thumbnail', 'full'];
  const s = sizes as Record<string, { source_url?: string } | undefined>;
  for (const key of preferred) {
    const u = s[key]?.source_url;
    if (typeof u === 'string' && u.trim() !== '') {
      return u;
    }
  }
  for (const entry of Object.values(s)) {
    const u = entry?.source_url;
    if (typeof u === 'string' && u.trim() !== '') {
      return u;
    }
  }
  return null;
}

/**
 * Featured-image URL from a WP REST post (`?_embed=1`), including fallbacks when
 * `source_url` is omitted but `media_details.sizes` is present.
 */
export function wpFeaturedThumbnailUrl(post: WpPost): string | null {
  const emb = post._embedded?.['wp:featuredmedia']?.[0];
  if (!emb || typeof emb !== 'object') {
    return null;
  }
  const m = emb as Record<string, unknown>;
  const top = m.source_url;
  if (typeof top === 'string' && top.trim() !== '') {
    return top;
  }
  const md = m.media_details;
  if (md && typeof md === 'object') {
    const fromSizes = firstSizeUrl((md as Record<string, unknown>).sizes);
    if (fromSizes) {
      return fromSizes;
    }
  }
  return null;
}
