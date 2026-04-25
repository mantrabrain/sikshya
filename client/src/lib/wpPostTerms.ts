import type { WpPost } from '../types';

/** Taxonomy term objects from `?_embed=1` on post collections. */
type WpEmbeddedTerm = { id?: number; name?: string; taxonomy?: string; slug?: string };

/**
 * Read embedded term names for a taxonomy from a WP REST post (`_embedded['wp:term']`).
 */
export function embeddedTermNames(post: WpPost, taxonomy: string): string[] {
  const raw = post._embedded?.['wp:term'];
  if (!Array.isArray(raw)) {
    return [];
  }
  for (const group of raw) {
    if (!Array.isArray(group) || group.length === 0) {
      continue;
    }
    const first = group[0] as WpEmbeddedTerm;
    if (first?.taxonomy === taxonomy) {
      return group
        .map((t) => (t as WpEmbeddedTerm).name)
        .filter((n): n is string => typeof n === 'string' && n.length > 0);
    }
  }
  return [];
}
