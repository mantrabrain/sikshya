import { getWpApi } from '../api';
import type { WpTerm } from '../types';
import { useAsyncData } from './useAsyncData';

export type WpTermCollectionQuery = {
  /** REST base segment, e.g. `sikshya_course_category`. */
  taxonomyRestBase: string;
  search: string;
  orderby: 'name' | 'count';
  order: 'asc' | 'desc';
  page?: number;
  perPage?: number;
  /** Increment to force refetch after creates/updates elsewhere. */
  refreshNonce?: number;
};

/**
 * WordPress `/wp/v2/{taxonomyRestBase}` term collection.
 */
export function useWpTermCollection(query: WpTermCollectionQuery) {
  const { taxonomyRestBase, search, orderby, order, page = 1, perPage = 20, refreshNonce = 0 } = query;

  return useAsyncData(async () => {
    const params = new URLSearchParams({
      per_page: String(perPage),
      page: String(page),
      orderby,
      order,
      hide_empty: 'false',
    });
    const q = search.trim();
    if (q) {
      params.set('search', q);
    }
    const path = `/${taxonomyRestBase.replace(/^\//, '')}?${params.toString()}`;
    return getWpApi().getWithTotal<WpTerm[]>(path);
  }, [taxonomyRestBase, search, orderby, order, page, perPage, refreshNonce]);
}
