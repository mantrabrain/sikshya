import type { SikshyaReactConfig, WpTerm } from '../types';

const TAXONOMY = 'sikshya_course_category';

/**
 * Public URL for a course category archive (matches `get_term_link()` on the server).
 */
export function courseCategoryViewHref(term: WpTerm, config: SikshyaReactConfig): string | null {
  const fromField =
    typeof term.sikshya_category_view_url === 'string' ? term.sikshya_category_view_url.trim() : '';
  if (fromField && fromField !== '#') {
    return fromField;
  }

  const fromLink = typeof term.link === 'string' ? term.link.trim() : '';
  if (fromLink && fromLink !== '#') {
    return fromLink;
  }

  const slug = (term.slug || '').trim();
  if (!slug) {
    return null;
  }

  const site = (config.siteUrl || '').replace(/\/$/, '');
  if (!site) {
    return null;
  }

  if (config.plainPermalinks) {
    return `${site}/?${TAXONOMY}=${encodeURIComponent(slug)}`;
  }

  const base = String(config.permalinks?.rewrite_tax_course_category || 'course-category').replace(/^\/+|\/+$/g, '');
  return `${site}/${base}/${encodeURIComponent(slug)}/`;
}
