import { getWpApi } from '../api';
import { WP_ENDPOINTS } from '../api/endpoints';
import type { WpPost } from '../types';
import { useAsyncData } from './useAsyncData';
import type { WpPostCollectionStatus } from './useWpPostStatusCounts';

export type WpPostCollectionQuery = {
  search: string;
  status: WpPostCollectionStatus;
  orderby: string;
  order: 'asc' | 'desc';
  /** REST `page` (1-based). */
  page?: number;
  perPage?: number;
  /** WordPress REST `_embed` (e.g. `1` loads embedded resources such as featured media). */
  embed?: string;
  /**
   * WordPress REST `_fields` selector — use when you need meta/embedded fields reliably in collections.
   * Example: `id,title,slug,status,meta,_embedded,date,modified,excerpt,author`
   */
  fields?: string;
  /**
   * Sikshya admin filter: course type for `sik_course` collections.
   * - `bundle`: only bundles
   * - `regular`: everything except bundles
   */
  sikshya_course_type?: 'bundle' | 'subscription' | 'regular' | '';
};

/**
 * Fetches a WP `/wp/v2/{restBase}` collection with filters (used by entity list pages).
 */
export function useWpPostCollection(restBase: string, query: WpPostCollectionQuery) {
  const { search, status, orderby, order, page = 1, perPage = 20, embed, fields, sikshya_course_type } = query;

  return useAsyncData(async () => {
    const params: Record<string, string | number | boolean> = {
      per_page: perPage,
      page,
      context: 'edit',
      orderby,
      order,
      status,
    };
    const q = search.trim();
    if (q) {
      params.search = q;
    }
    if (embed && embed !== '') {
      params._embed = embed;
    }
    const f = (fields || '').trim();
    if (f) {
      params._fields = f;
    }
    const ct = (sikshya_course_type || '').trim();
    if (ct) {
      params.sikshya_course_type = ct;
    }

    const path = WP_ENDPOINTS.postTypeCollection(restBase, params);
    return getWpApi().getWithTotal<WpPost[]>(path);
  }, [restBase, search, status, orderby, order, page, perPage, embed, fields, sikshya_course_type]);
}
