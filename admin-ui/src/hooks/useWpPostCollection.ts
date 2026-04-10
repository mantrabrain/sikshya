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
  perPage?: number;
  /** WordPress REST `_embed` (e.g. `1` loads embedded resources such as featured media). */
  embed?: string;
};

/**
 * Fetches a WP `/wp/v2/{restBase}` collection with filters (used by entity list pages).
 */
export function useWpPostCollection(restBase: string, query: WpPostCollectionQuery) {
  const { search, status, orderby, order, perPage = 50, embed } = query;

  return useAsyncData(async () => {
    const params: Record<string, string | number | boolean> = {
      per_page: perPage,
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

    const path = WP_ENDPOINTS.postTypeCollection(restBase, params);
    return getWpApi().getWithTotal<WpPost[]>(path);
  }, [restBase, search, status, orderby, order, perPage, embed]);
}
